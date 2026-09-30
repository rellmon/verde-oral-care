<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DentistSeeder extends Seeder
{
    public function run(): void
    {
        $dentists = [
            [
                'name' => 'Dr. Maria Santos',
                'email' => 'maria.santos@verdeoralcare.test',
                'phone' => '0917 000 0001',
                'license_number' => 'PRC-0100001',
                'specialization' => 'General Dentistry',
                'biography' => 'Dr. Santos focuses on preventive care and family dentistry.',
            ],
            [
                'name' => 'Dr. Rafael Cruz',
                'email' => 'rafael.cruz@verdeoralcare.test',
                'phone' => '0917 000 0002',
                'license_number' => 'PRC-0100002',
                'specialization' => 'Orthodontics',
                'biography' => 'Dr. Cruz specializes in braces and aligners for teens and adults.',
            ],
            [
                'name' => 'Dr. Angela Reyes',
                'email' => 'angela.reyes@verdeoralcare.test',
                'phone' => '0917 000 0003',
                'license_number' => 'PRC-0100003',
                'specialization' => 'Endodontics',
                'biography' => 'Dr. Reyes treats tooth pain and performs root canal procedures.',
            ],
            [
                'name' => 'Dr. Paolo Lim',
                'email' => 'paolo.lim@verdeoralcare.test',
                'phone' => '0917 000 0004',
                'license_number' => 'PRC-0100004',
                'specialization' => 'Pediatric Dentistry',
                'biography' => 'Dr. Lim provides gentle dental care for infants and children.',
            ],
        ];

        foreach ($dentists as $data) {
            $user = User::factory()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => 'dentist',
            ]);

            $dentist = $user->dentist()->create([
                'license_number' => $data['license_number'],
                'specialization' => $data['specialization'],
                'biography' => $data['biography'],
            ]);

            // Monday (1) to Friday (5): 9 AM - 5 PM. Saturday (6): 9 AM - 1 PM.
            // Sunday (0) gets no row, which means the dentist is off.
            for ($day = 1; $day <= 6; $day++) {
                $dentist->schedules()->create([
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => $day === 6 ? '13:00' : '17:00',
                ]);
            }
        }
    }
}