<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00. Monday 2026-10-05 is next week and Sunday 2026-10-04 is closed.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    }

    private function availability(Dentist $dentist, Service $service, string $date): array
    {
        return $this->getJson("/api/dentists/{$dentist->id}/availability?service_id={$service->id}&date={$date}")
            ->assertOk()
            ->json('data');
    }

    private function startTimes(Dentist $dentist, Service $service, string $date): array
    {
        return collect($this->availability($dentist, $service, $date))->pluck('start_time')->all();
    }

    // ---- Dentists ----

    public function test_the_dentist_list_shows_active_dentists_with_public_details_only(): void
    {
        $first = Dentist::factory()->create();
        $second = Dentist::factory()->create();
        Dentist::factory()->inactive()->create();

        $response = $this->getJson('/api/dentists')->assertOk();

        $this->assertSame([$first->id, $second->id], $response->json('data.*.id'));
        $this->assertSame(
            ['biography', 'id', 'name', 'profile_image', 'specialization'],
            collect($response->json('data.0'))->keys()->sort()->values()->all()
        );
    }

    public function test_a_dentist_profile_shows_the_weekly_hours_without_private_details(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();

        $response = $this->getJson("/api/dentists/{$dentist->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $dentist->user->name)
            ->assertJsonPath('data.specialization', $dentist->specialization)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.license_number')
            ->assertJsonMissingPath('data.is_active');

        // Sunday has no row, which means closed.
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            $response->json('data.schedules.*.day_name')
        );
        $response->assertJsonPath('data.schedules.0.start_time', '09:00')
            ->assertJsonPath('data.schedules.5.end_time', '13:00');
    }

    public function test_an_inactive_or_unknown_dentist_is_not_found(): void
    {
        $inactive = Dentist::factory()->inactive()->create();

        $this->getJson("/api/dentists/{$inactive->id}")->assertNotFound();
        $this->getJson('/api/dentists/999999')->assertNotFound();
    }

    // ---- Available times ----

    public function test_a_weekday_offers_a_slot_every_half_hour_from_opening_to_closing(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->create(); // 30 minutes

        $slots = $this->availability($dentist, $service, '2026-10-05');

        $this->assertCount(16, $slots); // 09:00 to 16:30
        $this->assertSame(['start_time' => '09:00', 'end_time' => '09:30'], $slots[0]);
        $this->assertSame(['start_time' => '16:30', 'end_time' => '17:00'], $slots[15]);
    }

    public function test_saturday_ends_at_one_oclock(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->create();

        $starts = $this->startTimes($dentist, $service, '2026-10-03');

        $this->assertCount(8, $starts);
        $this->assertSame('09:00', $starts[0]);
        $this->assertSame('12:30', $starts[7]);
    }

    public function test_sunday_is_closed(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->create();

        $this->assertSame([], $this->availability($dentist, $service, '2026-10-04'));
    }

    public function test_a_longer_service_stops_offering_starts_that_would_run_past_closing(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->lasting(60)->create();

        $slots = $this->availability($dentist, $service, '2026-10-05');

        $this->assertCount(15, $slots); // 09:00 to 16:00
        $this->assertSame(['start_time' => '09:00', 'end_time' => '10:00'], $slots[0]);
        $this->assertSame('16:00', $slots[14]['start_time']);
    }

    public function test_today_only_offers_times_after_the_current_time(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->create();

        // It is 10:00 now, so 10:00 itself is already gone.
        $starts = $this->startTimes($dentist, $service, '2026-10-01');

        $this->assertCount(13, $starts); // 10:30 to 16:30
        $this->assertSame('10:30', $starts[0]);
    }

    public function test_a_dentist_with_no_working_hours_has_no_times(): void
    {
        $dentist = Dentist::factory()->create();
        $service = Service::factory()->create();

        $this->assertSame([], $this->availability($dentist, $service, '2026-10-05'));
    }

    public function test_an_inactive_dentist_has_no_availability_page(): void
    {
        $dentist = Dentist::factory()->inactive()->withStandardSchedule()->create();
        $service = Service::factory()->create();

        $this->getJson("/api/dentists/{$dentist->id}/availability?service_id={$service->id}&date=2026-10-05")
            ->assertNotFound();
    }

    public static function invalidAvailabilityQueries(): array
    {
        return [
            'missing service' => ['date=2026-10-05', 'service_id'],
            'unknown service' => ['service_id=999999&date=2026-10-05', 'service_id'],
            'missing date' => ['service_id={service}', 'date'],
            'wrong date format' => ['service_id={service}&date=05-10-2026', 'date'],
            'date in the past' => ['service_id={service}&date=2026-09-30', 'date'],
        ];
    }

    #[DataProvider('invalidAvailabilityQueries')]
    public function test_an_invalid_availability_question_is_refused(string $query, string $errorField): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->create();

        $this->getJson("/api/dentists/{$dentist->id}/availability?".str_replace('{service}', (string) $service->id, $query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);
    }

    public function test_an_inactive_service_cannot_be_asked_about(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->inactive()->create();

        $this->getJson("/api/dentists/{$dentist->id}/availability?service_id={$service->id}&date=2026-10-05")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }
}
