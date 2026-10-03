<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDentistsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 1 October 2026, 10:00. Monday 2026-10-05 and Tuesday 2026-10-06 are next week.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function dentistData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dr. Test Dentist',
            'email' => 'test.dentist@example.test',
            'phone' => '09171234567',
            'password' => 'password123',
            'license_number' => 'PRC-TEST-001',
            'specialization' => 'Orthodontics',
            'biography' => 'Test biography.',
        ], $overrides);
    }

    // The edit form sends every field again, so an update starts from the dentist's current values.
    private function updateData(Dentist $dentist, array $overrides = []): array
    {
        return array_merge([
            'name' => $dentist->user->name,
            'email' => $dentist->user->email,
            'phone' => $dentist->user->phone,
            'license_number' => $dentist->license_number,
            'specialization' => $dentist->specialization,
            'biography' => $dentist->biography,
        ], $overrides);
    }

    private function publicDentistIds(): array
    {
        return $this->getJson('/api/dentists')->assertOk()->json('data.*.id');
    }

    // ---- The admin list ----

    public function test_the_admin_list_includes_inactive_dentists_and_private_details(): void
    {
        $active = Dentist::factory()->create();
        $inactive = Dentist::factory()->inactive()->create();

        $response = $this->getJson('/api/admin/dentists')->assertOk();

        $this->assertEqualsCanonicalizing([$active->id, $inactive->id], $response->json('data.*.id'));
        $response->assertJsonPath('data.0.email', $active->user->email)
            ->assertJsonPath('data.0.license_number', $active->license_number)
            ->assertJsonPath('data.1.is_active', false);
    }

    // ---- Creating ----

    public function test_an_admin_can_create_a_dentist(): void
    {
        $this->postJson('/api/admin/dentists', $this->dentistData())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Dr. Test Dentist')
            ->assertJsonPath('data.email', 'test.dentist@example.test')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'test.dentist@example.test')->sole();

        $this->assertSame('dentist', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertDatabaseHas('dentists', ['user_id' => $user->id, 'license_number' => 'PRC-TEST-001']);

        // The new dentist is visible to patients.
        $this->assertContains($user->dentist->id, $this->publicDentistIds());
    }

    public function test_the_role_cannot_be_chosen_when_creating_a_dentist(): void
    {
        $this->postJson('/api/admin/dentists', $this->dentistData(['role' => 'admin']))->assertCreated();

        $this->assertSame('dentist', User::where('email', 'test.dentist@example.test')->sole()->role);
    }

    public static function invalidDentists(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'password too short' => [['password' => 'short'], 'password'],
            'missing password' => [['password' => ''], 'password'],
            'missing license number' => [['license_number' => ''], 'license_number'],
            'missing specialization' => [['specialization' => ''], 'specialization'],
        ];
    }

    #[DataProvider('invalidDentists')]
    public function test_an_invalid_dentist_is_refused(array $overrides, string $errorField): void
    {
        $this->postJson('/api/admin/dentists', $this->dentistData($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        $this->assertSame(0, Dentist::count());
        $this->assertSame(0, User::where('role', 'dentist')->count());
    }

    public function test_an_email_or_license_number_that_is_already_used_is_refused(): void
    {
        $existing = Dentist::factory()->create();

        $this->postJson('/api/admin/dentists', $this->dentistData(['email' => $existing->user->email]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/admin/dentists', $this->dentistData(['license_number' => $existing->license_number]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);

        $this->assertSame(1, Dentist::count());
    }

    // ---- Editing ----

    public function test_a_dentist_can_be_saved_without_changing_their_email_or_license_number(): void
    {
        $dentist = Dentist::factory()->create();

        // Their own email and license number are not "already taken".
        $this->putJson(
            "/api/admin/dentists/{$dentist->id}",
            $this->updateData($dentist, ['specialization' => 'Endodontics'])
        )
            ->assertOk()
            ->assertJsonPath('data.specialization', 'Endodontics');

        $this->assertSame('Endodontics', $dentist->fresh()->specialization);
    }

    public function test_a_dentist_cannot_take_another_dentists_email_or_license_number(): void
    {
        $dentist = Dentist::factory()->create();
        $other = Dentist::factory()->create();

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['email' => $other->user->email]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['license_number' => $other->license_number]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);
    }

    public function test_a_blank_password_keeps_the_current_one_and_a_new_one_replaces_it(): void
    {
        $dentist = Dentist::factory()->create(); // the factory password is "password"

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['password' => '']))
            ->assertOk();

        $this->assertTrue(Hash::check('password', $dentist->user->fresh()->password));

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['password' => 'newpassword123']))
            ->assertOk();

        $user = $dentist->user->fresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_editing_a_dentist_cannot_change_their_role(): void
    {
        $dentist = Dentist::factory()->create();

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['role' => 'admin']))
            ->assertOk();

        $this->assertSame('dentist', $dentist->user->fresh()->role);
    }

    public function test_deactivating_a_dentist_hides_them_from_patients_but_not_from_the_admin(): void
    {
        $dentist = Dentist::factory()->create();
        $this->assertContains($dentist->id, $this->publicDentistIds());

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertNotContains($dentist->id, $this->publicDentistIds());
        $this->getJson("/api/dentists/{$dentist->id}")->assertNotFound();
        $this->assertContains($dentist->id, $this->getJson('/api/admin/dentists')->json('data.*.id'));

        // Saving again without mentioning is_active must not switch them back on.
        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist))->assertOk();
        $this->assertFalse($dentist->fresh()->is_active);

        $this->putJson("/api/admin/dentists/{$dentist->id}", $this->updateData($dentist, ['is_active' => true]))->assertOk();
        $this->assertContains($dentist->id, $this->publicDentistIds());
    }

    public function test_the_public_list_hides_private_dentist_details(): void
    {
        Dentist::factory()->create();

        $this->getJson('/api/dentists')
            ->assertOk()
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.license_number')
            ->assertJsonMissingPath('data.0.is_active');
    }

    // ---- Working hours ----

    public function test_the_admin_can_read_a_dentists_working_hours(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();

        $response = $this->getJson("/api/admin/dentists/{$dentist->id}/schedules")->assertOk();

        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            $response->json('data.*.day_name')
        );
        $response->assertJsonPath('data.0.start_time', '09:00')->assertJsonPath('data.5.end_time', '13:00');
    }

    public function test_saving_working_hours_replaces_the_whole_week_and_changes_what_can_be_booked(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $other = Dentist::factory()->withStandardSchedule()->create();
        $service = Service::factory()->create(); // 30 minutes

        $this->putJson("/api/admin/dentists/{$dentist->id}/schedules", ['schedules' => [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['day_of_week' => 3, 'start_time' => '13:00', 'end_time' => '17:00'],
        ]])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.day_name', 'Monday')
            ->assertJsonPath('data.1.day_name', 'Wednesday');

        $this->assertSame(2, $dentist->schedules()->count());
        $this->assertSame(6, $other->schedules()->count()); // another dentist is untouched

        $slots = fn (string $date) => collect(
            $this->getJson("/api/dentists/{$dentist->id}/availability?service_id={$service->id}&date={$date}")
                ->assertOk()
                ->json('data')
        )->pluck('start_time')->all();

        // Monday is now 9 to 12, and Tuesday is closed.
        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'], $slots('2026-10-05'));
        $this->assertSame([], $slots('2026-10-06'));
    }

    public function test_an_empty_week_means_the_dentist_has_no_working_days(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();

        $this->putJson("/api/admin/dentists/{$dentist->id}/schedules", ['schedules' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(0, $dentist->schedules()->count());
    }

    public static function invalidSchedules(): array
    {
        $eightDays = array_merge(
            array_map(fn ($day) => ['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '10:00'], range(0, 6)),
            [['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '10:00']]
        );

        return [
            'closing before opening' => [
                [['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '09:00']],
                'schedules.0.end_time',
                'The closing time must be later than the opening time.',
            ],
            'badly formatted time' => [
                [['day_of_week' => 2, 'start_time' => '9am', 'end_time' => '17:00']],
                'schedules.0.start_time',
                'Enter the opening time as hours and minutes, for example 09:00.',
            ],
            'missing closing time' => [
                [['day_of_week' => 2, 'start_time' => '09:00']],
                'schedules.0.end_time',
                'Enter a closing time.',
            ],
            'the same day twice' => [
                [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                    ['day_of_week' => 1, 'start_time' => '13:00', 'end_time' => '17:00'],
                ],
                'schedules.0.day_of_week',
                'Each day can only be listed once.',
            ],
            'more than seven days' => [$eightDays, 'schedules', 'A week has at most seven days.'],
        ];
    }

    #[DataProvider('invalidSchedules')]
    public function test_an_invalid_week_is_refused_with_a_plain_message_and_the_old_hours_survive(
        array $schedules,
        string $errorKey,
        string $message
    ): void {
        $dentist = Dentist::factory()->withStandardSchedule()->create();

        $this->putJson("/api/admin/dentists/{$dentist->id}/schedules", ['schedules' => $schedules])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey => $message]);

        $this->assertSame(6, $dentist->schedules()->count());
    }
}
