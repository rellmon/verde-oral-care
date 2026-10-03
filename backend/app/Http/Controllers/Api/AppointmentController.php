<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Service;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['required', 'in:upcoming,history'],
        ]);

        // Start from the logged-in patient's own appointments, never from all appointments.
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'Your account has no patient profile.');

        $today = Carbon::today()->toDateString();
        $activeStatuses = [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED];

        $query = $patient->appointments()->with(['dentist.user', 'service']);

        if ($request->query('type') === 'upcoming') {
            $query->where('appointment_date', '>=', $today)
                ->whereIn('status', $activeStatuses)
                ->orderBy('appointment_date')
                ->orderBy('start_time');
        } else {
            $query->where(function ($query) use ($today, $activeStatuses) {
                $query->where('appointment_date', '<', $today)
                    ->orWhereNotIn('status', $activeStatuses);
            })
                ->orderByDesc('appointment_date')
                ->orderByDesc('start_time');
        }

        return AppointmentResource::collection($query->get());
    }

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

    public function cancel(Request $request, Appointment $appointment)
    {
        // 1. Authorization: is this the logged-in patient's own appointment? (AppointmentPolicy)
        Gate::authorize('cancel', $appointment);

        // 2. Business rule: is cancelling still allowed?
        abort_unless(
            $appointment->canBeCancelled(),
            422,
            'This appointment can no longer be cancelled online. Appointments can be cancelled while pending or confirmed, at least '
                .Appointment::MIN_HOURS_BEFORE_CANCEL.' hours before the start time.'
        );

        $appointment->status = Appointment::STATUS_CANCELLED; // status is not mass-assignable
        $appointment->save();

        $appointment->load(['dentist.user', 'service']);

        return new AppointmentResource($appointment);
    }
}
