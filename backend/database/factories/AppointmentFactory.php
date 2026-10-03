<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'dentist_id' => Dentist::factory(),
            'service_id' => Service::factory(),
            'appointment_date' => now()->addDays(7)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'reason' => fake()->sentence(),
            'status' => Appointment::STATUS_PENDING,
            'dentist_notes' => null,
        ];
    }
}
