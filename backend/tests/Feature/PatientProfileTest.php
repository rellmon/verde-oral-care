<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PatientProfileTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->patient = Patient::factory()->create();
    }

    private function actAsPatient(): void
    {
        Sanctum::actingAs($this->patient->user);
    }

    private function profileData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Name',
            'phone' => '09170000001',
            'date_of_birth' => '1995-03-14',
            'gender' => 'female',
            'address' => '12 Sample Street, Pasig City',
            'emergency_contact_name' => 'Ana Demo',
            'emergency_contact_phone' => '09171112222',
        ], $overrides);
    }

    public function test_a_patient_can_read_their_profile(): void
    {
        $this->actAsPatient();
        $patient = $this->patient->fresh();

        $this->getJson('/api/patient/profile')
            ->assertOk()
            ->assertExactJson(['data' => [
                'name' => $patient->user->name,
                'email' => $patient->user->email,
                'phone' => $patient->user->phone,
                'date_of_birth' => $patient->date_of_birth->toDateString(),
                'gender' => $patient->gender,
                'address' => $patient->address,
                'emergency_contact_name' => $patient->emergency_contact_name,
                'emergency_contact_phone' => $patient->emergency_contact_phone,
            ]]);
    }

    public function test_a_patient_can_update_their_profile_across_both_tables(): void
    {
        $this->actAsPatient();
        $originalEmail = $this->patient->user->email;

        $this->putJson('/api/patient/profile', $this->profileData())
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', $originalEmail)
            ->assertJsonPath('data.date_of_birth', '1995-03-14')
            ->assertJsonPath('data.emergency_contact_name', 'Ana Demo');

        // The name and phone live on the users table, the rest on the patients table.
        $this->assertDatabaseHas('users', [
            'id' => $this->patient->user_id,
            'name' => 'Updated Name',
            'phone' => '09170000001',
            'email' => $originalEmail,
        ]);
        $this->assertDatabaseHas('patients', [
            'id' => $this->patient->id,
            'date_of_birth' => '1995-03-14',
            'gender' => 'female',
            'address' => '12 Sample Street, Pasig City',
            'emergency_contact_name' => 'Ana Demo',
            'emergency_contact_phone' => '09171112222',
        ]);
    }

    public function test_the_new_name_shows_up_in_the_current_user(): void
    {
        $this->actAsPatient();

        $this->putJson('/api/patient/profile', $this->profileData(['name' => 'Brand New Name']))->assertOk();

        $this->getJson('/api/user')->assertOk()->assertJsonPath('name', 'Brand New Name');
    }

    public function test_the_email_and_role_cannot_be_changed_here(): void
    {
        $this->actAsPatient();
        $originalEmail = $this->patient->user->email;

        $this->putJson('/api/patient/profile', $this->profileData([
            'email' => 'hacker@example.test',
            'role' => 'admin',
        ]))->assertOk();

        $user = $this->patient->user->fresh();
        $this->assertSame($originalEmail, $user->email);
        $this->assertSame('patient', $user->role);
    }

    public function test_empty_optional_fields_clear_the_stored_values(): void
    {
        $this->actAsPatient();

        $this->putJson('/api/patient/profile', [
            'name' => 'Only A Name',
            'phone' => '',
            'date_of_birth' => '',
            'gender' => '',
            'address' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.date_of_birth', null);

        $patient = $this->patient->fresh();
        $this->assertNull($patient->date_of_birth);
        $this->assertNull($patient->gender);
        $this->assertNull($patient->address);
        $this->assertNull($patient->emergency_contact_name);
        $this->assertNull($patient->emergency_contact_phone);
        $this->assertNull($patient->user->phone);
    }

    public static function invalidProfiles(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'phone too long' => [['phone' => str_repeat('1', 21)], 'phone'],
            'date of birth in the future' => [['date_of_birth' => '2026-10-02'], 'date_of_birth'],
            'badly formatted date of birth' => [['date_of_birth' => '14/03/1995'], 'date_of_birth'],
            'unknown gender' => [['gender' => 'robot'], 'gender'],
            'address too long' => [['address' => str_repeat('a', 256)], 'address'],
            'emergency phone too long' => [['emergency_contact_phone' => str_repeat('1', 21)], 'emergency_contact_phone'],
        ];
    }

    #[DataProvider('invalidProfiles')]
    public function test_an_invalid_profile_is_refused_and_nothing_is_saved(array $overrides, string $errorField): void
    {
        $this->actAsPatient();
        $originalName = $this->patient->user->name;

        $this->putJson('/api/patient/profile', $this->profileData($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        $this->assertSame($originalName, $this->patient->user->fresh()->name);
    }

    public function test_a_patient_born_today_is_accepted(): void
    {
        $this->actAsPatient();

        $this->putJson('/api/patient/profile', $this->profileData(['date_of_birth' => '2026-10-01']))->assertOk();
    }

    public function test_one_patient_cannot_change_another_patients_profile(): void
    {
        $other = Patient::factory()->create();
        $otherName = $other->user->name;

        $this->actAsPatient();
        $this->putJson('/api/patient/profile', $this->profileData())->assertOk();

        // There is no id in the URL, so the update can only ever reach the logged-in patient.
        $this->assertSame($otherName, $other->user->fresh()->name);
    }

    public function test_guests_cannot_see_or_change_a_profile(): void
    {
        $this->getJson('/api/patient/profile')->assertUnauthorized();
        $this->putJson('/api/patient/profile', $this->profileData())->assertUnauthorized();
    }

    public function test_dentists_and_admins_have_no_patient_profile(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->dentist()->create()] as $staff) {
            Sanctum::actingAs($staff);

            $this->getJson('/api/patient/profile')->assertForbidden();
            $this->putJson('/api/patient/profile', $this->profileData())->assertForbidden();
        }
    }

    public function test_a_patient_account_without_a_patient_row_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->create()); // the patient role, but no patients row

        $this->getJson('/api/patient/profile')->assertForbidden();
        $this->putJson('/api/patient/profile', $this->profileData())->assertForbidden();
    }
}
