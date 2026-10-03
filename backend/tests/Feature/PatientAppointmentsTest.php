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

class PatientAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private Dentist $dentist;

    private Patient $patient;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00. Every date in these tests is relative to this moment.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->dentist = Dentist::factory()->withStandardSchedule()->create();
        $this->patient = Patient::factory()->create();
        $this->service = Service::factory()->create();
    }

    private function appointment(array $attributes = [], ?Patient $patient = null): Appointment
    {
        return Appointment::factory()
            ->for($this->dentist)
            ->for($patient ?? $this->patient)
            ->for($this->service)
            ->create($attributes);
    }

    public function test_a_patient_sees_only_their_own_appointments_split_into_upcoming_and_history(): void
    {
        $upcoming = $this->appointment(['appointment_date' => '2026-10-05', 'status' => 'pending']);
        $past = $this->appointment(['appointment_date' => '2026-09-20', 'status' => 'completed']);
        $cancelled = $this->appointment(['appointment_date' => '2026-10-09', 'status' => 'cancelled']);
        $this->appointment(['appointment_date' => '2026-10-05'], Patient::factory()->create()); // someone else's

        Sanctum::actingAs($this->patient->user);

        $this->assertEqualsCanonicalizing(
            [$upcoming->id],
            $this->getJson('/api/appointments?type=upcoming')->assertOk()->json('data.*.id')
        );
        $this->assertEqualsCanonicalizing(
            [$past->id, $cancelled->id],
            $this->getJson('/api/appointments?type=history')->assertOk()->json('data.*.id')
        );
    }

    public function test_the_list_tells_the_page_which_appointments_can_still_be_cancelled(): void
    {
        $far = $this->appointment(['appointment_date' => '2026-10-05']);
        $near = $this->appointment(['appointment_date' => '2026-10-02', 'start_time' => '09:00:00', 'end_time' => '09:30:00']);

        Sanctum::actingAs($this->patient->user);

        $flags = collect($this->getJson('/api/appointments?type=upcoming')->assertOk()->json('data'))
            ->pluck('can_cancel', 'id');

        $this->assertTrue($flags[$far->id]);
        $this->assertFalse($flags[$near->id]); // 23 hours away
    }

    public function test_a_patient_never_sees_the_dentists_private_notes(): void
    {
        $this->appointment([
            'appointment_date' => '2026-09-20',
            'status' => 'completed',
            'dentist_notes' => 'Patient is anxious about needles',
        ]);

        Sanctum::actingAs($this->patient->user);

        $response = $this->getJson('/api/appointments?type=history')->assertOk();

        $response->assertJsonMissingPath('data.0.dentist_notes');
        $this->assertStringNotContainsString('anxious', $response->getContent());
    }

    public function test_the_list_rejects_an_unknown_type(): void
    {
        Sanctum::actingAs($this->patient->user);

        $this->getJson('/api/appointments?type=everything')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_only_patients_can_list_appointments(): void
    {
        $this->getJson('/api/appointments?type=upcoming')->assertUnauthorized();

        foreach ([User::factory()->admin()->create(), User::factory()->dentist()->create()] as $staff) {
            Sanctum::actingAs($staff);

            $this->getJson('/api/appointments?type=upcoming')->assertForbidden();
        }
    }

    public static function cancellableStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'confirmed' => ['confirmed'],
        ];
    }

    #[DataProvider('cancellableStatuses')]
    public function test_a_patient_can_cancel_their_own_appointment(string $status): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05', 'status' => $status]);

        Sanctum::actingAs($this->patient->user);

        $this->patchJson("/api/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false);

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_a_patient_cannot_cancel_someone_elses_appointment(): void
    {
        $theirs = $this->appointment(['appointment_date' => '2026-10-05'], Patient::factory()->create());

        Sanctum::actingAs($this->patient->user);

        $this->patchJson("/api/appointments/{$theirs->id}/cancel")->assertForbidden();

        $this->assertSame('pending', $theirs->fresh()->status);
    }

    public function test_cancelling_closes_exactly_24_hours_before_the_start(): void
    {
        // The clock says 2026-10-01 10:00, so 2026-10-02 10:00 is exactly 24 hours away.
        $tooLate = $this->appointment([
            'appointment_date' => '2026-10-02',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);
        $justInTime = $this->appointment([
            'appointment_date' => '2026-10-02',
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
        ]);

        Sanctum::actingAs($this->patient->user);

        $this->patchJson("/api/appointments/{$tooLate->id}/cancel")->assertUnprocessable();
        $this->patchJson("/api/appointments/{$justInTime->id}/cancel")->assertOk();

        $this->assertSame('pending', $tooLate->fresh()->status);
        $this->assertSame('cancelled', $justInTime->fresh()->status);
    }

    public static function finishedStatuses(): array
    {
        return [
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
            'no-show' => ['no_show'],
        ];
    }

    #[DataProvider('finishedStatuses')]
    public function test_a_finished_appointment_cannot_be_cancelled(string $status): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05', 'status' => $status]);

        Sanctum::actingAs($this->patient->user);

        $this->patchJson("/api/appointments/{$appointment->id}/cancel")->assertUnprocessable();

        $this->assertSame($status, $appointment->fresh()->status);
    }

    public function test_only_patients_can_cancel_appointments(): void
    {
        $appointment = $this->appointment(['appointment_date' => '2026-10-05']);

        $this->patchJson("/api/appointments/{$appointment->id}/cancel")->assertUnauthorized();

        foreach ([User::factory()->admin()->create(), User::factory()->dentist()->create()] as $staff) {
            Sanctum::actingAs($staff);

            $this->patchJson("/api/appointments/{$appointment->id}/cancel")->assertForbidden();
        }

        $this->assertSame('pending', $appointment->fresh()->status);
    }
}
