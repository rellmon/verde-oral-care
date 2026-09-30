<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Clinic Admin',
            'email' => 'admin@verdeoralcare.test',
            'phone' => '0917 000 0100',
            'role' => 'admin',
        ]);

        // A patient with a known login for testing
        $demoPatient = User::factory()->create([
            'name' => 'Demo Patient',
            'email' => 'patient@verdeoralcare.test',
            'phone' => '0917 000 0101',
            'role' => 'patient',
        ]);

        $demoPatient->patient()->create([
            'date_of_birth' => '1995-06-15',
            'gender' => 'female',
            'address' => 'Mandaluyong City, Metro Manila',
            'emergency_contact_name' => 'Demo Contact',
            'emergency_contact_phone' => '0917 000 0102',
        ]);

        // A few more patients with random names
        User::factory()->count(5)->create(['role' => 'patient'])->each(function ($user) {
            $user->patient()->create([
                'date_of_birth' => fake()->dateTimeBetween('-70 years', '-5 years')->format('Y-m-d'),
                'gender' => fake()->randomElement(['male', 'female']),
                'address' => fake()->address(),
            ]);
        });
    }
}