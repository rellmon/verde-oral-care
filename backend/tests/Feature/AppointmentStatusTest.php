<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private Dentist $dentist;

    private Patient $patient;

    private Service $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->dentist = Dentist::factory()->withStandardSchedule()->create();
        $this->patient = Patient::factory()->create();
        $this->service = Service::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function appointment(array $attributes = [], ?Dentist $dentist = null): Appointment
    {
        return Appointment::factory()
            ->for($dentist ?? $this->dentist)
            ->for($this->patient)
            ->for($this->service)
            ->create($attributes);
    }

    // An appointment that began at 09:00 today. The clock says 10:00, so it has started.
    private function startedAppointment(string $status): Appointment
    {
        return $this->appointment([
            'appointment_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => $status,
        ]);
    }

    // [from, to]: status changes that the transition table does not allow.
    public static function forbiddenTransitions(): array
    {
        return [
            'completed back to confirmed' => ['completed', 'confirmed'],
            'cancelled back to confirmed' => ['cancelled', 'confirmed'],
            'completed to cancelled' => ['completed', 'cancelled'],
            'no-show to completed' => ['no_show', 'completed'],
            'pending straight to completed' => ['pending', 'completed'],
            'pending straight to no-show' => ['pending', 'no_show'],
        ];
    }

    // ---- Dentist ----

    public function test_a_dentist_can_confirm_their_pending_appointment(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05', 'status' => 'pending']);

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            // The appointment is in the future, so completing and no-show are not offered yet.
            ->assertJsonPath('data.available_statuses', ['cancelled']);
    }

    public function test_a_dentist_can_complete_a_started_appointment_and_save_notes(): void
    {
        $appointment = $this->startedAppointment('confirmed');

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", [
            'status' => 'completed',
            'dentist_notes' => 'Cleaning done. Advised daily flossing.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.available_statuses', []);

        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);
        $this->assertSame('Cleaning done. Advised daily flossing.', $appointment->dentist_notes);
    }

    public function test_a_dentist_cannot_complete_an_appointment_that_has_not_started(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05', 'status' => 'confirmed']);

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_a_dentist_cannot_make_a_forbidden_status_change(string $from, string $to): void
    {
        $appointment = $this->startedAppointment($from);

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['status' => $to])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame($from, $appointment->fresh()->status);
    }

    public function test_a_status_can_never_go_back_to_pending_or_be_made_up(): void
    {
        $appointment = $this->startedAppointment('confirmed');

        Sanctum::actingAs($this->dentist->user);

        foreach (['pending', 'robot'] as $status) {
            $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_notes_can_be_saved_without_changing_the_status(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05', 'status' => 'confirmed']);

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['dentist_notes' => 'Bring previous X-rays.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.dentist_notes', 'Bring previous X-rays.');
    }

    public function test_an_empty_update_is_refused(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05']);

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", [])->assertUnprocessable();
    }

    public function test_a_dentist_cannot_touch_another_dentists_appointment(): void
    {
        $otherDentist = Dentist::factory()->withStandardSchedule()->create();
        $theirs = $this->appointment(['appointment_date' => '2026-10-05'], $otherDentist);

        Sanctum::actingAs($this->dentist->user);

        $this->patchJson("/api/dentist/appointments/{$theirs->id}", ['status' => 'confirmed'])->assertForbidden();
        $this->patchJson("/api/dentist/appointments/{$theirs->id}", ['dentist_notes' => 'Not mine'])->assertForbidden();

        $theirs->refresh();
        $this->assertSame('pending', $theirs->status);
        $this->assertNull($theirs->dentist_notes);
    }

    public function test_only_dentists_can_use_the_dentist_endpoints(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05']);

        $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['status' => 'confirmed'])->assertUnauthorized();

        foreach ([$this->patient->user, $this->admin] as $user) {
            Sanctum::actingAs($user);

            $this->patchJson("/api/dentist/appointments/{$appointment->id}", ['status' => 'confirmed'])->assertForbidden();
        }

        $this->assertSame('pending', $appointment->fresh()->status);
    }

    public function test_a_dentist_sees_only_their_own_appointments_for_today(): void
    {
        $mine = $this->startedAppointment('confirmed');
        $this->startedAppointment('cancelled'); // cancelled ones are left out of "today"
        $this->appointment([
            'appointment_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
        ], Dentist::factory()->create()); // another dentist's

        Sanctum::actingAs($this->dentist->user);

        $this->assertEqualsCanonicalizing(
            [$mine->id],
            $this->getJson('/api/dentist/appointments?type=today')->assertOk()->json('data.*.id')
        );
    }

    // ---- Admin ----

    public function test_an_admin_can_confirm_any_appointment(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05']);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_an_admin_can_cancel_late_where_the_patient_cannot(): void
    {
        // Starts at 15:00 today, five hours from now: inside the patient's 24-hour limit.
        $appointment = $this->appointment([
            'appointment_date' => '2026-10-01',
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($this->patient->user);
        $this->patchJson("/api/appointments/{$appointment->id}/cancel")->assertUnprocessable();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_an_admin_cannot_complete_an_appointment_that_has_not_started(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05', 'status' => 'confirmed']);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_an_admin_cannot_make_a_forbidden_status_change(string $from, string $to): void
    {
        $appointment = $this->startedAppointment($from);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", ['status' => $to])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame($from, $appointment->fresh()->status);
    }

    public function test_only_admins_can_use_the_admin_status_endpoint(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05']);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'confirmed'])->assertUnauthorized();

        foreach ([$this->patient->user, $this->dentist->user] as $user) {
            Sanctum::actingAs($user);

            $this->patchJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'confirmed'])->assertForbidden();
        }

        $this->assertSame('pending', $appointment->fresh()->status);
    }
}
