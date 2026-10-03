<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function serviceData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Gum Treatment',
            'description' => 'Deep cleaning below the gum line.',
            'price' => 1500,
            'duration_minutes' => 45,
            'is_active' => true,
        ], $overrides);
    }

    private function publicServiceIds(): array
    {
        return $this->getJson('/api/services')->assertOk()->json('data.*.id');
    }

    public function test_the_admin_list_includes_inactive_services_but_the_public_list_does_not(): void
    {
        $active = Service::factory()->create();
        $inactive = Service::factory()->inactive()->create();

        $this->assertEqualsCanonicalizing(
            [$active->id, $inactive->id],
            $this->getJson('/api/admin/services')->assertOk()->json('data.*.id')
        );

        $this->assertSame([$active->id], $this->publicServiceIds());
    }

    public function test_an_admin_can_create_a_service(): void
    {
        $this->postJson('/api/admin/services', $this->serviceData())->assertSuccessful();

        $this->assertDatabaseHas('services', [
            'name' => 'Gum Treatment',
            'duration_minutes' => 45,
            'price' => 1500,
            'is_active' => true,
        ]);

        $this->assertContains(
            'Gum Treatment',
            $this->getJson('/api/services')->json('data.*.name')
        );
    }

    public static function invalidServices(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'missing price' => [['price' => ''], 'price'],
            'missing duration' => [['duration_minutes' => ''], 'duration_minutes'],
        ];
    }

    #[DataProvider('invalidServices')]
    public function test_an_invalid_service_is_refused(array $overrides, string $errorField): void
    {
        $this->postJson('/api/admin/services', $this->serviceData($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        $this->assertSame(0, Service::count());
    }

    public function test_an_admin_can_edit_a_service(): void
    {
        $service = Service::factory()->create();

        $this->putJson("/api/admin/services/{$service->id}", $this->serviceData(['name' => 'Renamed Service', 'duration_minutes' => 60]))
            ->assertSuccessful();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Renamed Service',
            'duration_minutes' => 60,
        ]);
    }

    public function test_deactivating_a_service_hides_it_from_patients_but_not_from_the_admin(): void
    {
        $service = Service::factory()->create();
        $this->assertContains($service->id, $this->publicServiceIds());

        $this->putJson("/api/admin/services/{$service->id}", $this->serviceData(['is_active' => false]))
            ->assertSuccessful();

        $this->assertNotContains($service->id, $this->publicServiceIds());
        $this->assertContains($service->id, $this->getJson('/api/admin/services')->json('data.*.id'));
    }

    public function test_an_unused_service_can_be_deleted(): void
    {
        $service = Service::factory()->create();

        $this->deleteJson("/api/admin/services/{$service->id}")->assertSuccessful();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_a_service_that_has_appointments_cannot_be_deleted(): void
    {
        $service = Service::factory()->create();
        Appointment::factory()->for($service)->create();

        $this->deleteJson("/api/admin/services/{$service->id}")->assertStatus(409);

        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }
}
