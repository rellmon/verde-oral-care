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

class AdminAppointmentsListTest extends TestCase
{
    use RefreshDatabase;

    private Dentist $dentist;

    private Dentist $otherDentist;

    private Patient $patient;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->dentist = Dentist::factory()->create();
        $this->otherDentist = Dentist::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->service = Service::factory()->create();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function appointment(string $date, string $start, string $status, ?Dentist $dentist = null): Appointment
    {
        return Appointment::factory()
            ->for($dentist ?? $this->dentist)
            ->for($this->patient)
            ->for($this->service)
            ->create([
                'appointment_date' => $date,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addMinutes(30)->format('H:i:s'),
                'status' => $status,
            ]);
    }

    private function ids(string $query = ''): array
    {
        return $this->getJson('/api/admin/appointments'.($query === '' ? '' : "?{$query}"))
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_each_row_shows_the_patient_dentist_service_and_what_can_be_done(): void
    {
        $this->appointment('2026-10-05', '09:00:00', 'pending');

        $this->getJson('/api/admin/appointments')
            ->assertOk()
            ->assertJsonPath('data.0.patient.name', $this->patient->user->name)
            ->assertJsonPath('data.0.patient.email', $this->patient->user->email)
            ->assertJsonPath('data.0.patient.phone', $this->patient->user->phone)
            ->assertJsonPath('data.0.dentist.name', $this->dentist->user->name)
            ->assertJsonPath('data.0.service.name', $this->service->name)
            ->assertJsonPath('data.0.available_statuses', ['confirmed', 'cancelled'])
            ->assertJsonPath('data.0.can_reschedule', true)
            ->assertJsonMissingPath('data.0.dentist_notes');
    }

    public function test_newest_first_by_default_and_oldest_first_on_request(): void
    {
        $early = $this->appointment('2026-10-01', '09:00:00', 'completed');
        $morning = $this->appointment('2026-10-03', '09:00:00', 'pending');
        $afternoon = $this->appointment('2026-10-03', '14:00:00', 'pending');

        $this->assertSame([$afternoon->id, $morning->id, $early->id], $this->ids());
        $this->assertSame([$early->id, $morning->id, $afternoon->id], $this->ids('sort=asc'));
    }

    public function test_the_filters_work_alone_and_together(): void
    {
        $a = $this->appointment('2026-10-02', '09:00:00', 'pending');
        $b = $this->appointment('2026-10-05', '09:00:00', 'confirmed');
        $c = $this->appointment('2026-10-05', '10:00:00', 'pending', $this->otherDentist);
        $d = $this->appointment('2026-10-10', '09:00:00', 'cancelled', $this->otherDentist);

        $this->assertEqualsCanonicalizing([$a->id, $c->id], $this->ids('status=pending'));
        $this->assertEqualsCanonicalizing([$c->id, $d->id], $this->ids("dentist_id={$this->otherDentist->id}"));
        $this->assertEqualsCanonicalizing([$b->id, $c->id, $d->id], $this->ids('date_from=2026-10-05'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $this->ids('date_to=2026-10-05'));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $this->ids('date_from=2026-10-05&date_to=2026-10-05'));
        $this->assertEqualsCanonicalizing(
            [$c->id],
            $this->ids("status=pending&dentist_id={$this->otherDentist->id}&date_from=2026-10-05")
        );
        $this->assertSame([], $this->ids('status=no_show'));
    }

    public function test_the_list_is_paginated_and_the_filters_survive_in_the_page_links(): void
    {
        Appointment::factory()->count(16)
            ->for($this->dentist)->for($this->patient)->for($this->service)
            ->create(['status' => 'pending', 'appointment_date' => '2026-10-05']);

        $first = $this->getJson('/api/admin/appointments?status=pending')->assertOk();

        $this->assertCount(15, $first->json('data'));
        $this->assertSame(16, $first->json('meta.total'));
        $this->assertSame(2, $first->json('meta.last_page'));
        $this->assertStringContainsString('status=pending', $first->json('links.next'));

        $this->assertCount(1, $this->getJson('/api/admin/appointments?status=pending&page=2')->json('data'));
    }

    public static function invalidFilters(): array
    {
        return [
            'unknown status' => ['status=robot', 'status'],
            'unknown dentist' => ['dentist_id=999999', 'dentist_id'],
            'end date before start date' => ['date_from=2026-10-10&date_to=2026-10-01', 'date_to'],
            'badly formatted date' => ['date_from=10%2F05%2F2026', 'date_from'],
            'unknown sort order' => ['sort=sideways', 'sort'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_an_invalid_filter_is_refused(string $query, string $errorField): void
    {
        $this->getJson("/api/admin/appointments?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);
    }

    public function test_no_matches_gives_an_empty_list(): void
    {
        $this->getJson('/api/admin/appointments')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
