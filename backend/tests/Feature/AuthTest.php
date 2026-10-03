<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Patient',
            'email' => 'new.patient@example.test',
            'phone' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ---- Registration ----

    public function test_a_visitor_can_register_as_a_patient(): void
    {
        $response = $this->postJson('/api/register', $this->registrationData())
            ->assertCreated()
            ->assertJsonPath('user.role', 'patient')
            ->assertJsonPath('user.email', 'new.patient@example.test');

        $this->assertNotEmpty($response->json('token'));

        $user = User::where('email', 'new.patient@example.test')->sole();

        // The account gets a patient profile, and the password is stored hashed.
        $this->assertDatabaseHas('patients', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNotSame('password123', $user->password);
    }

    public function test_the_response_only_contains_the_fields_the_app_needs(): void
    {
        $user = $this->postJson('/api/register', $this->registrationData())->assertCreated()->json('user');

        $this->assertSame(['email', 'id', 'name', 'phone', 'role'], collect($user)->keys()->sort()->values()->all());
    }

    public function test_a_client_cannot_choose_its_own_role(): void
    {
        $this->postJson('/api/register', $this->registrationData(['role' => 'admin']))
            ->assertCreated()
            ->assertJsonPath('user.role', 'patient');

        $this->assertSame('patient', User::where('email', 'new.patient@example.test')->sole()->role);
    }

    public function test_an_email_address_can_only_be_registered_once(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->postJson('/api/register', $this->registrationData(['email' => 'taken@example.test']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public static function invalidRegistrations(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'missing password' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'password too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'password confirmation does not match' => [['password_confirmation' => 'different123'], 'password'],
        ];
    }

    #[DataProvider('invalidRegistrations')]
    public function test_invalid_registrations_are_refused(array $overrides, string $errorField): void
    {
        $this->postJson('/api/register', $this->registrationData($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        $this->assertSame(0, User::count());
    }

    // ---- Login, logout and the current user ----

    public function test_a_user_can_log_in_and_use_the_token(): void
    {
        $user = User::factory()->create(['email' => 'login@example.test']);

        $response = $this->postJson('/api/login', ['email' => 'login@example.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'patient');

        $this->withToken($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'login@example.test');
    }

    public function test_wrong_credentials_get_the_same_message_whether_the_email_exists_or_not(): void
    {
        User::factory()->create(['email' => 'real@example.test']);

        $wrongPassword = $this->postJson('/api/login', ['email' => 'real@example.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->json('errors.email.0');

        $unknownEmail = $this->postJson('/api/login', ['email' => 'nobody@example.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->json('errors.email.0');

        // The two answers must be identical, so nobody can find out which addresses have accounts.
        $this->assertSame($wrongPassword, $unknownEmail);
    }

    public function test_the_current_user_endpoint_returns_only_the_safe_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => 'patient',
            ]);
    }

    public function test_logging_out_deletes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->assertSame(1, $user->tokens()->count());

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    // ---- Token expiry ----

    public function test_tokens_are_set_to_expire_after_seven_days(): void
    {
        $this->assertSame(10080, config('sanctum.expiration')); // minutes
    }

    public function test_a_token_just_inside_the_limit_still_works(): void
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;

        $this->travel(10079)->minutes();

        $this->withToken($token)->getJson('/api/user')->assertOk();
    }

    public function test_a_token_older_than_seven_days_is_rejected(): void
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;

        $this->travel(8)->days();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    // ---- Rate limits ----

    public function test_login_is_limited_to_five_attempts_a_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'x@example.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'x@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_registration_has_its_own_limit_and_does_not_block_logins(): void
    {
        User::factory()->create(['email' => 'login@example.test']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/register', [])->assertTooManyRequests();

        // Registration is used up, but a real login still works.
        $this->postJson('/api/login', ['email' => 'login@example.test', 'password' => 'password'])->assertOk();
    }

    public function test_the_public_pages_have_their_own_limit_and_do_not_block_logins(): void
    {
        User::factory()->create(['email' => 'login@example.test']);

        for ($request = 0; $request < 120; $request++) {
            $this->getJson('/api/services')->assertOk();
        }

        $this->getJson('/api/services')->assertTooManyRequests();

        $this->postJson('/api/login', ['email' => 'login@example.test', 'password' => 'password'])->assertOk();
    }
}
