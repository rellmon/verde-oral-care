<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Service;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function store(StoreAppointmentRequest $request, AvailabilityService $availability)
    {
        // The patient comes from the logged-in user, never from the request body.
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'Your account has no patient profile.');

        $data = $request->validated();
        $service = Service::findOrFail($data['service_id']);
        $date = Carbon::createFromFormat('Y-m-d', $data['appointment_date'])->startOfDay();

        $appointment = DB::transaction(function () use ($data, $service, $date, $patient, $availability) {
            // Lock this dentist's row: simultaneous bookings for the same dentist now run one at a time.
            $dentist = Dentist::whereKey($data['dentist_id'])->lockForUpdate()->firstOrFail();

            // Re-check the slot now that we hold the lock.
            $slot = collect($availability->getAvailableSlots($dentist, $service, $date))
                ->firstWhere('start_time', $data['start_time']);

            if (! $slot) {
                throw ValidationException::withMessages([
                    'start_time' => 'That time is no longer available. Please choose another.',
                ]);
            }

            $appointment = new Appointment([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'service_id' => $service->id,
                'appointment_date' => $data['appointment_date'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'reason' => $data['reason'],
            ]);
            $appointment->status = Appointment::STATUS_PENDING; // status is not mass-assignable
            $appointment->save();

            return $appointment;
        });

        $appointment->load(['dentist.user', 'service']);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }
}