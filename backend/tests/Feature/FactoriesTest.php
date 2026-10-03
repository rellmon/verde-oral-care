<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_build_a_working_clinic(): void
    {
        $dentist = Dentist::factory()->withStandardSchedule()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->lasting(60)->create();

        $appointment = Appointment::factory()
            ->for($dentist)
            ->for($patient)
            ->for($service)
            ->create();

        $this->assertSame('dentist', $dentist->user->role);
        $this->assertSame('patient', $patient->user->role);
        $this->assertCount(6, $dentist->schedules);
        $this->assertSame(60, $service->duration_minutes);
        $this->assertSame('pending', $appointment->status);
        $this->assertTrue($appointment->dentist->is($dentist));
        $this->assertTrue($appointment->patient->is($patient));
    }

    public function test_user_factory_can_make_each_role(): void
    {
        $this->assertSame('admin', User::factory()->admin()->create()->role);
        $this->assertSame('dentist', User::factory()->dentist()->create()->role);
        $this->assertSame('patient', User::factory()->create()->role);
    }
}
