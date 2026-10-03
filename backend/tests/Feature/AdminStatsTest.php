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

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_the_dashboard_numbers_count_the_right_appointments(): void
    {
        $dentist = Dentist::factory()->create();
        Dentist::factory()->inactive()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        Service::factory()->inactive()->create();

        // [date, start time, status]
        $appointments = [
            ['2026-10-01', '09:00:00', 'confirmed'], // today
            ['2026-10-01', '10:00:00', 'cancelled'], // today, but cancelled: not counted as today
            ['2026-10-01', '11:00:00', 'pending'],   // today
            ['2026-09-30', '09:00:00', 'pending'],   // yesterday and still pending: not "waiting" any more
            ['2026-10-02', '09:00:00', 'pending'],   // tomorrow
            ['2026-10-03', '09:00:00', 'completed'], // not an active status
            ['2026-10-08', '09:00:00', 'confirmed'], // exactly 7 days from today: inside the week
            ['2026-10-09', '09:00:00', 'confirmed'], // 8 days from today: outside the week
        ];

        foreach ($appointments as [$date, $start, $status]) {
            Appointment::factory()->for($dentist)->for($patient)->for($service)->create([
                'appointment_date' => $date,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addMinutes(30)->format('H:i:s'),
                'status' => $status,
            ]);
        }

        $response = $this->getJson('/api/admin/stats')->assertOk();

        // Today: the confirmed and the pending one. The cancelled one is left out.
        $response->assertJsonPath('data.appointments_today', 2);
        // Pending from today onwards: 11:00 today and tomorrow. Yesterday's is not counted.
        $response->assertJsonPath('data.pending_confirmation', 2);
        // After today, up to and including the 8th: tomorrow's pending and the confirmed one on the 8th.
        $response->assertJsonPath('data.upcoming_week', 2);

        $response->assertJsonPath('data.total_patients', 1);
        $response->assertJsonPath('data.active_dentists', 1);
        $response->assertJsonPath('data.active_services', 1);

        $this->assertEquals([
            'pending' => 3,
            'confirmed' => 3,
            'completed' => 1,
            'cancelled' => 1,
            'no_show' => 0,
        ], $response->json('data.appointments_by_status'));
    }

    public function test_an_empty_clinic_shows_zeros_and_every_status(): void
    {
        $response = $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.appointments_today', 0)
            ->assertJsonPath('data.total_patients', 0);

        $this->assertEquals([
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
        ], $response->json('data.appointments_by_status'));
    }
}
