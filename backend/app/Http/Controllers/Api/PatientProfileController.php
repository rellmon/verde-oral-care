<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePatientProfileRequest;
use App\Http\Resources\PatientProfileResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientProfileController extends Controller
{
    public function show(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'Your account has no patient profile.');

        $patient->load('user');

        return new PatientProfileResource($patient);
    }

    public function update(UpdatePatientProfileRequest $request)
    {
        $user = $request->user();
        $patient = $user->patient;
        abort_unless($patient, 403, 'Your account has no patient profile.');

        $data = $request->validated();

        DB::transaction(function () use ($user, $patient, $data) {
            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ]);

            $patient->update([
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'address' => $data['address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ]);
        });

        // Reload from the database so the response shows exactly what was saved.
        $patient->load('user');

        return new PatientProfileResource($patient);
    }
}