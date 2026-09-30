<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Dental Consultation', 'description' => 'Initial exam and treatment planning with a dentist.', 'price' => 500, 'duration_minutes' => 30],
            ['name' => 'Dental Check-up', 'description' => 'Routine exam of your teeth and gums.', 'price' => 600, 'duration_minutes' => 30],
            ['name' => 'Teeth Cleaning', 'description' => 'Professional removal of plaque and tartar.', 'price' => 1500, 'duration_minutes' => 45],
            ['name' => 'Dental Filling', 'description' => 'Repair of a cavity with tooth-colored filling material.', 'price' => 2000, 'duration_minutes' => 45],
            ['name' => 'Tooth Extraction', 'description' => 'Removal of a damaged or problem tooth.', 'price' => 2500, 'duration_minutes' => 45],
            ['name' => 'Dental X-Ray', 'description' => 'Digital imaging to see below the surface of your teeth.', 'price' => 800, 'duration_minutes' => 15],
            ['name' => 'Root Canal Treatment', 'description' => 'Treatment of an infected tooth to save it from extraction.', 'price' => 8000, 'duration_minutes' => 90],
            ['name' => 'Teeth Whitening', 'description' => 'In-clinic whitening for a brighter smile.', 'price' => 6000, 'duration_minutes' => 60],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}