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

class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    private Dentist $dentist;

    private Patient $patient;

    private Service $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00. Monday 2026-10-05 and Tuesday 2026-10-06 are next week.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->dentist = Dentist::factory()->withStandardSchedule()->create();
        $this->patient = Patient::factory()->create();
        $this->service = Service::factory()->create(); // 30 minutes
        $this->admin = User::factory()->admin()->create();
    }

    // By default: Monday 5 October, 10:00 to 10:30.
    private function appointment(array $attributes = [], ?Service $service = null): Appointment
    {
        return Appointment::factory()
            ->for($this->dentist)
            ->for($this->patient)
            ->for($service ?? $this->service)
            ->create(array_merge([
                'appointment_date' => '2026-10-05',
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
            ], $attributes));
    }

    private function slotsUrl(Appointment $appointment, string $date): string
    {
        return "/api/admin/appointments/{$appointment->id}/slots?date={$date}";
    }

    private function rescheduleUrl(Appointment $appointment): string
    {
        return "/api/admin/appointments/{$appointment->id}/reschedule";
    }

    // ---- The list of times an appointment could move to ----

    public function test_the_slots_list_offers_the_appointments_own_time_but_not_other_peoples(): void
    {
        $mine = $this->appointment();
        $this->appointment(['start_time' => '11:00:00', 'end_time' => '11:30:00', 'status' => 'confirmed']);
        $this->appointment(['start_time' => '14:00:00', 'end_time' => '14:30:00', 'status' => 'cancelled']);

        Sanctum::actingAs($this->admin);

        $starts = collect($this->getJson($this->slotsUrl($mine, '2026-10-05'))->assertOk()->json('data'))
            ->pluck('start_time');

        $this->assertTrue($starts->contains('10:00'), 'its own time must be offered');
        $this->assertFalse($starts->contains('11:00'), 'another appointment blocks its time');
        $this->assertTrue($starts->contains('14:00'), 'a cancelled appointment frees its time');
    }

    public function test_the_slots_list_needs_a_valid_future_date(): void
    {
        $appointment = $this->appointment();
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/admin/appointments/{$appointment->id}/slots")->assertUnprocessable();
        $this->getJson($this->slotsUrl($appointment, '2026-09-30'))->assertUnprocessable();
    }

    // ---- Moving an appointment ----

    public function test_an_admin_can_move_an_appointment_and_the_old_time_becomes_free(): void
    {
        $appointment = $this->appointment(['status' => 'confirmed']);

        Sanctum::actingAs($this->admin);

        $this->patchJson($this->rescheduleUrl($appointment), ['appointment_date' => '2026-10-06', 'start_time' => '15:00'])
            ->assertOk()
            ->assertJsonPath('data.appointment_date', '2026-10-06')
            ->assertJsonPath('data.start_time', '15:00')
            ->assertJsonPath('data.end_time', '15:30')
            ->assertJsonPath('data.status', 'confirmed');

        // Only the date and time changed.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'service_id' => $this->service->id,
            'appointment_date' => '2026-10-06',
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',
            'status' => 'confirmed',
        ]);

        // The old time can be booked again.
        $url = "/api/dentists/{$this->dentist->id}/availability?service_id={$this->service->id}&date=2026-10-05";
        $starts = collect($this->getJson($url)->assertOk()->json('data'))->pluck('start_time');

        $this->assertTrue($starts->contains('10:00'));
    }

    public function test_a_long_appointment_can_shift_by_half_an_hour_without_blocking_itself(): void
    {
        $longService = Service::factory()->lasting(60)->create();
        $appointment = $this->appointment(['end_time' => '11:00:00'], $longService); // 10:00 to 11:00

        Sanctum::actingAs($this->admin);

        // 10:30 to 11:30 overlaps its own current time, which must not count as a conflict.
        $this->patchJson($this->rescheduleUrl($appointment), ['appointment_date' => '2026-10-05', 'start_time' => '10:30'])
            ->assertOk()
            ->assertJsonPath('data.start_time', '10:30')
            ->assertJsonPath('data.end_time', '11:30');
    }

    public function test_an_appointment_cannot_be_moved_onto_another_appointments_time(): void
    {
        $mine = $this->appointment();
        $this->appointment(['start_time' => '11:00:00', 'end_time' => '11:30:00', 'status' => 'confirmed']);

        Sanctum::actingAs($this->admin);

        $this->patchJson($this->rescheduleUrl($mine), ['appointment_date' => '2026-10-05', 'start_time' => '11:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->assertSame('10:00:00', $mine->fresh()->start_time);
    }

    public function test_moving_to_the_same_time_is_refused_with_a_clear_message(): void
    {
        $appointment = $this->appointment();

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson($this->rescheduleUrl($appointment), [
            'appointment_date' => '2026-10-05',
            'start_time' => '10:00',
        ])->assertUnprocessable();

        $this->assertSame('The appointment is already scheduled at that time.', $response->json('errors.start_time.0'));
    }

    public static function invalidMoves(): array
    {
        return [
            'date in the past' => [['appointment_date' => '2026-09-30', 'start_time' => '10:00'], 'appointment_date'],
            'closed on Sunday' => [['appointment_date' => '2026-10-04', 'start_time' => '10:00'], 'start_time'],
            'not on the 30-minute grid' => [['appointment_date' => '2026-10-06', 'start_time' => '10:15'], 'start_time'],
            'after closing time' => [['appointment_date' => '2026-10-06', 'start_time' => '17:00'], 'start_time'],
            'earlier today' => [['appointment_date' => '2026-10-01', 'start_time' => '09:30'], 'start_time'],
            'missing time' => [['appointment_date' => '2026-10-06'], 'start_time'],
            'missing date' => [['start_time' => '10:00'], 'appointment_date'],
        ];
    }

    #[DataProvider('invalidMoves')]
    public function test_invalid_moves_are_refused_and_change_nothing(array $payload, string $errorField): void
    {
        $appointment = $this->appointment();

        Sanctum::actingAs($this->admin);

        $this->patchJson($this->rescheduleUrl($appointment), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        $appointment->refresh();
        $this->assertSame('2026-10-05', $appointment->appointment_date->toDateString());
        $this->assertSame('10:00:00', $appointment->start_time);
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
    public function test_a_finished_appointment_cannot_be_moved(string $status): void
    {
        $appointment = $this->appointment(['status' => $status]);

        Sanctum::actingAs($this->admin);

        $this->patchJson($this->rescheduleUrl($appointment), ['appointment_date' => '2026-10-06', 'start_time' => '15:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->getJson($this->slotsUrl($appointment, '2026-10-06'))->assertUnprocessable();

        $this->assertSame('2026-10-05', $appointment->fresh()->appointment_date->toDateString());
    }

    // ---- Access ----

    public function test_only_admins_can_reschedule(): void
    {
        $appointment = $this->appointment();
        $payload = ['appointment_date' => '2026-10-06', 'start_time' => '15:00'];

        $this->patchJson($this->rescheduleUrl($appointment), $payload)->assertUnauthorized();
        $this->getJson($this->slotsUrl($appointment, '2026-10-06'))->assertUnauthorized();

        foreach ([$this->patient->user, $this->dentist->user] as $user) {
            Sanctum::actingAs($user);

            $this->patchJson($this->rescheduleUrl($appointment), $payload)->assertForbidden();
            $this->getJson($this->slotsUrl($appointment, '2026-10-06'))->assertForbidden();
        }

        $this->assertSame('2026-10-05', $appointment->fresh()->appointment_date->toDateString());
    }
}
