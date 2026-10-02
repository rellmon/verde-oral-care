<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAppointmentResource;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Appointment::STATUS_TRANSITIONS))],
            'dentist_id' => ['sometimes', 'integer', 'exists:dentists,id'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['sometimes', 'in:asc,desc'],
        ]);

        $query = Appointment::with(['patient.user', 'dentist.user', 'service']);

        // Each filter is only applied when it was sent. Filters combine (AND).
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['dentist_id'])) {
            $query->where('dentist_id', $filters['dentist_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('appointment_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('appointment_date', '<=', $filters['date_to']);
        }

        $direction = $filters['sort'] ?? 'desc';
        $query->orderBy('appointment_date', $direction)->orderBy('start_time', $direction);

        // withQueryString() keeps the filters in the pagination links.
        return AdminAppointmentResource::collection($query->paginate(15)->withQueryString());
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        // 'pending' is not allowed: an appointment can never go back to pending.
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    Appointment::STATUS_CONFIRMED,
                    Appointment::STATUS_COMPLETED,
                    Appointment::STATUS_NO_SHOW,
                    Appointment::STATUS_CANCELLED,
                ]),
            ],
        ]);

        DB::transaction(function () use ($appointment, $data) {
            // Lock the row and re-read it, in case the dentist changed it a moment ago.
            $current = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            $error = $current->statusChangeError($data['status']);

            if ($error) {
                throw ValidationException::withMessages(['status' => $error]);
            }

            $current->status = $data['status']; // status is not mass-assignable
            $current->save();
        });

        $appointment->refresh()->load(['patient.user', 'dentist.user', 'service']);

        return new AdminAppointmentResource($appointment);
    }

    // The times this appointment could be moved to on a given day.
    public function slots(Request $request, Appointment $appointment, AvailabilityService $availability)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        abort_unless(
            $appointment->canBeRescheduled(),
            422,
            'Only pending or confirmed appointments can be rescheduled.'
        );

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();

        // The appointment's own time is ignored, so it does not block itself.
        $slots = $availability->getAvailableSlots(
            $appointment->dentist,
            $appointment->service,
            $date,
            $appointment->id
        );

        return response()->json(['data' => $slots]);
    }

    // Moves the appointment to a new date and start time (same dentist, same service).
    public function reschedule(Request $request, Appointment $appointment, AvailabilityService $availability)
    {
        $data = $request->validate([
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['appointment_date'])->startOfDay();

        DB::transaction(function () use ($appointment, $data, $date, $availability) {
            // Lock the dentist's row, as booking does, so a reschedule and a new booking cannot collide.
            $dentist = Dentist::whereKey($appointment->dentist_id)->lockForUpdate()->firstOrFail();

            // Re-read the appointment now that we hold the lock.
            $current = Appointment::with('service')->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (! $current->canBeRescheduled()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending or confirmed appointments can be rescheduled.',
                ]);
            }

            $slot = collect($availability->getAvailableSlots($dentist, $current->service, $date, $current->id))
                ->firstWhere('start_time', $data['start_time']);

            if (! $slot) {
                throw ValidationException::withMessages([
                    'start_time' => 'That time is not available. Please choose another.',
                ]);
            }

            $isSameTime = $current->appointment_date->toDateString() === $data['appointment_date']
                && substr($current->start_time, 0, 5) === $slot['start_time'];

            if ($isSameTime) {
                throw ValidationException::withMessages([
                    'start_time' => 'The appointment is already scheduled at that time.',
                ]);
            }

            $current->appointment_date = $data['appointment_date'];
            $current->start_time = $slot['start_time'];
            $current->end_time = $slot['end_time']; // always start + the service's duration
            $current->save();
        });

        $appointment->refresh()->load(['patient.user', 'dentist.user', 'service']);

        return new AdminAppointmentResource($appointment);
    }
}