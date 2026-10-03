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

class AdminPatientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function patientNamed(string $name, string $email, string $phone, array $patientDetails = []): Patient
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email, 'phone' => $phone]);

        return Patient::factory()->create(array_merge(['user_id' => $user->id], $patientDetails));
    }

    private function threePatients(): void
    {
        // Created out of alphabetical order on purpose.
        $this->patientNamed('Carol Cruz', 'carol@example.test', '09173330003');
        $this->patientNamed('Alice Anderson', 'alice@example.test', '09171110001');
        $this->patientNamed('Bob Brown', 'bob@example.test', '09172220002');
    }

    public function test_the_list_is_alphabetical_and_hides_sensitive_details(): void
    {
        $this->threePatients();

        $response = $this->getJson('/api/admin/patients')->assertOk();

        $this->assertSame(['Alice Anderson', 'Bob Brown', 'Carol Cruz'], $response->json('data.*.name'));

        $response->assertJsonPath('data.0.email', 'alice@example.test')
            ->assertJsonPath('data.0.phone', '09171110001')
            ->assertJsonPath('data.0.appointments_count', 0)
            // The list never carries these. They only appear when one patient is opened.
            ->assertJsonMissingPath('data.0.date_of_birth')
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.gender')
            ->assertJsonMissingPath('data.0.emergency_contact_name')
            ->assertJsonMissingPath('data.0.emergency_contact_phone')
            ->assertJsonMissingPath('data.0.appointments');
    }

    public function test_the_list_only_contains_patients_not_staff(): void
    {
        $this->threePatients();
        User::factory()->dentist()->create(['name' => 'Alice The Dentist']);
        User::factory()->admin()->create(['name' => 'Alice The Admin']);

        $this->assertSame(
            ['Alice Anderson'],
            $this->getJson('/api/admin/patients?search=alice')->assertOk()->json('data.*.name')
        );
    }

    public static function searches(): array
    {
        return [
            'part of a name' => ['alic', ['Alice Anderson']],
            'part of an email' => ['bob@example', ['Bob Brown']],
            'part of a phone number' => ['0003', ['Carol Cruz']],
            'something all three share' => ['example.test', ['Alice Anderson', 'Bob Brown', 'Carol Cruz']],
            'no match' => ['zzzzqqqq', []],
            'a lone percent sign is not a wildcard' => ['%', []],
            'a lone underscore is not a wildcard' => ['_', []],
        ];
    }

    #[DataProvider('searches')]
    public function test_searching_by_name_email_or_phone(string $term, array $expectedNames): void
    {
        $this->threePatients();

        $this->assertSame(
            $expectedNames,
            $this->getJson('/api/admin/patients?search='.urlencode($term))->assertOk()->json('data.*.name')
        );
    }

    public function test_the_list_is_paginated_fifteen_at_a_time(): void
    {
        Patient::factory()->count(16)->create();

        $first = $this->getJson('/api/admin/patients')->assertOk();
        $this->assertCount(15, $first->json('data'));
        $this->assertSame(16, $first->json('meta.total'));
        $this->assertSame(2, $first->json('meta.last_page'));

        $this->assertCount(1, $this->getJson('/api/admin/patients?page=2')->assertOk()->json('data'));
    }

    public function test_opening_a_patient_shows_their_details_and_history_newest_first(): void
    {
        $dentist = Dentist::factory()->create();
        $service = Service::factory()->create();

        $alice = $this->patientNamed('Alice Anderson', 'alice@example.test', '09171110001', [
            'date_of_birth' => '1990-05-17',
            'gender' => 'female',
            'address' => '12 Sample Street, Pasig City',
            'emergency_contact_name' => 'Ana Demo',
            'emergency_contact_phone' => '09171112222',
        ]);

        $older = Appointment::factory()->for($alice)->for($dentist)->for($service)
            ->create(['appointment_date' => '2026-09-20', 'status' => 'completed']);
        $newer = Appointment::factory()->for($alice)->for($dentist)->for($service)
            ->create(['appointment_date' => '2026-10-10', 'status' => 'pending']);
        // Someone else's appointment must not appear in Alice's history.
        Appointment::factory()->for($dentist)->for($service)->create(['appointment_date' => '2026-10-05']);

        $response = $this->getJson("/api/admin/patients/{$alice->id}")->assertOk();

        $response->assertJsonPath('data.name', 'Alice Anderson')
            ->assertJsonPath('data.date_of_birth', '1990-05-17')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.address', '12 Sample Street, Pasig City')
            ->assertJsonPath('data.emergency_contact_name', 'Ana Demo')
            ->assertJsonPath('data.appointments_count', 2);

        $this->assertSame([$newer->id, $older->id], $response->json('data.appointments.*.id'));
    }

    public function test_an_unknown_patient_is_not_found(): void
    {
        $this->getJson('/api/admin/patients/999999')->assertNotFound();
    }
}
