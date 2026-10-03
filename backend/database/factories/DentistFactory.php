<?php

namespace Database\Factories;

use App\Models\Dentist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dentist>
 */
class DentistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->dentist(),
            'license_number' => 'PRC-'.fake()->unique()->numerify('#######'),
            'specialization' => fake()->randomElement([
                'General Dentistry',
                'Orthodontics',
                'Endodontics',
                'Pediatric Dentistry',
            ]),
            'biography' => fake()->sentence(),
            'profile_image' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    // The same working week the seeders create: Monday to Friday 9-5, Saturday 9-1, Sunday closed.
    public function withStandardSchedule(): static
    {
        return $this->afterCreating(function (Dentist $dentist) {
            foreach ([1, 2, 3, 4, 5] as $day) {
                $dentist->schedules()->create([
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ]);
            }

            $dentist->schedules()->create([
                'day_of_week' => 6,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);
        });
    }
}
