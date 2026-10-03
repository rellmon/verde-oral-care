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
use Tests\TestCase;

class DentistPortalTest extends TestCase
{
    use RefreshDatabase;

    private Dentist $dentist;

    private Patient $patient;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->dentist = Dentist::factory()->withStandardSchedule()->create();
        $this->patient = Patient::factory()->create(['date_of_birth' => '1990-05-17']);
        $this->service = Service::factory()->create();
    }

    private function appointment(string $date, string $start, string $status, ?Dentist $dentist = null, array $extra = []): Appointment
    {
        return Appointment::factory()
            ->for($dentist ?? $this->dentist)
            ->for($this->patient)
            ->for($this->service)
            ->create(array_merge([
                'appointment_date' => $date,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addMinutes(30)->format('H:i:s'),
                'status' => $status,
            ], $extra));
    }

    private function tabIds(string $type): array
    {
        return $this->getJson("/api/dentist/appointments?type={$type}")->assertOk()->json('data.*.id');
    }

    public function test_every_appointment_appears_in_exactly_one_tab(): void
    {
        Sanctum::actingAs($this->dentist->user);

        // Past, today and future, in every status.
        $mine = [];
        $hour = 9;
        foreach (['2026-09-28', '2026-10-01', '2026-10-04'] as $date) {
            foreach (['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $status) {
                $mine[] = $this->appointment($date, sprintf('%02d:00:00', $hour++), $status)->id;
            }
        }

        // Another dentist's appointment must never appear.
        $this->appointment('2026-10-01', '09:00:00', 'pending', Dentist::factory()->create());

        $seen = array_merge($this->tabIds('today'), $this->tabIds('upcoming'), $this->tabIds('history'));

        // Fifteen in total means none is missing and none appears twice.
        $this->assertCount(15, $seen);
        $this->assertEqualsCanonicalizing($mine, $seen);
    }

    public function test_each_tab_holds_the_right_appointments_in_the_right_order(): void
    {
        Sanctum::actingAs($this->dentist->user);

        $lateToday = $this->appointment('2026-10-01', '11:00:00', 'confirmed');
        $earlyToday = $this->appointment('2026-10-01', '09:00:00', 'pending');

        $laterUpcoming = $this->appointment('2026-10-05', '09:00:00', 'pending');
        $afternoonUpcoming = $this->appointment('2026-10-03', '14:00:00', 'confirmed');
        $morningUpcoming = $this->appointment('2026-10-03', '09:00:00', 'pending');

        $oldest = $this->appointment('2026-09-20', '09:00:00', 'completed');
        $middle = $this->appointment('2026-09-28', '09:00:00', 'no_show');
        $cancelledToday = $this->appointment('2026-10-01', '12:00:00', 'cancelled');

        // Today is in time order, and a cancelled appointment is not part of the day's work.
        $this->assertSame([$earlyToday->id, $lateToday->id], $this->tabIds('today'));

        // Upcoming is soonest first.
        $this->assertSame(
            [$morningUpcoming->id, $afternoonUpcoming->id, $laterUpcoming->id],
            $this->tabIds('upcoming')
        );

        // History is newest first, and it includes an appointment that was cancelled today.
        $this->assertSame([$cancelledToday->id, $middle->id, $oldest->id], $this->tabIds('history'));
    }

    public function test_the_dentist_sees_only_what_they_need_about_the_patient(): void
    {
        $this->appointment('2026-10-05', '09:00:00', 'pending');

        Sanctum::actingAs($this->dentist->user);

        $response = $this->getJson('/api/dentist/appointments?type=upcoming')->assertOk();

        $this->assertSame(
            ['date_of_birth', 'name', 'phone'],
            collect($response->json('data.0.patient'))->keys()->sort()->values()->all()
        );
        $response->assertJsonPath('data.0.patient.date_of_birth', '1990-05-17')
            ->assertJsonMissingPath('data.0.patient.email')
            ->assertJsonMissingPath('data.0.patient.address');
        $this->assertStringNotContainsString($this->patient->user->email, $response->getContent());
    }

    public function test_the_dentist_sees_their_own_private_notes(): void
    {
        $this->appointment('2026-10-01', '09:00:00', 'confirmed', null, ['dentist_notes' => 'Anxious about needles']);

        Sanctum::actingAs($this->dentist->user);

        $this->getJson('/api/dentist/appointments?type=today')
            ->assertOk()
            ->assertJsonPath('data.0.dentist_notes', 'Anxious about needles');
    }

    public function test_an_unknown_or_missing_type_is_refused(): void
    {
        Sanctum::actingAs($this->dentist->user);

        $this->getJson('/api/dentist/appointments?type=everything')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
        $this->getJson('/api/dentist/appointments')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_a_dentist_account_without_a_dentist_row_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->dentist()->create()); // the dentist role, but no dentists row

        $this->getJson('/api/dentist/appointments?type=today')->assertForbidden();
        $this->getJson('/api/dentist/profile')->assertForbidden();
    }

    public function test_a_dentist_can_read_their_own_profile_and_working_hours(): void
    {
        Sanctum::actingAs($this->dentist->user);

        $response = $this->getJson('/api/dentist/profile')
            ->assertOk()
            ->assertJsonPath('data.name', $this->dentist->user->name)
            ->assertJsonPath('data.email', $this->dentist->user->email)
            ->assertJsonPath('data.license_number', $this->dentist->license_number)
            ->assertJsonPath('data.specialization', $this->dentist->specialization)
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            $response->json('data.schedules.*.day_name')
        );
    }

    public function test_a_deactivated_dentist_can_still_see_their_own_profile(): void
    {
        $inactive = Dentist::factory()->inactive()->create();
        Sanctum::actingAs($inactive->user);

        $this->getJson('/api/dentist/profile')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_each_dentist_sees_their_own_profile_and_nobody_elses(): void
    {
        $other = Dentist::factory()->create();

        Sanctum::actingAs($this->dentist->user);
        $this->getJson('/api/dentist/profile')->assertJsonPath('data.license_number', $this->dentist->license_number);

        Sanctum::actingAs($other->user);
        $this->getJson('/api/dentist/profile')->assertJsonPath('data.license_number', $other->license_number);
    }
}
