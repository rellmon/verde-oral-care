<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDentistAppointmentRequest;
use App\Http\Resources\DentistAppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DentistAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['required', 'in:today,upcoming,history'],
        ]);

        // Start from the logged-in dentist's own appointments, never from all appointments.
        $dentist = $request->user()->dentist;
        abort_unless($dentist, 403, 'Your account has no dentist profile.');

        $today = Carbon::today()->toDateString();
        $activeStatuses = [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED];
        $type = $request->query('type');

        $query = $dentist->appointments()->with(['patient.user', 'service']);

        if ($type === 'today') {
            // Everything on today's date except cancelled ones.
            $query->where('appointment_date', $today)
                ->where('status', '!=', Appointment::STATUS_CANCELLED)
                ->orderBy('start_time');
        } elseif ($type === 'upcoming') {
            $query->where('appointment_date', '>', $today)
                ->whereIn('status', $activeStatuses)
                ->orderBy('appointment_date')
                ->orderBy('start_time');
        } else {
            // History: past dates, future appointments that are no longer active, and
            // appointments cancelled today (the "today" tab leaves those out).
            $query->where(function ($query) use ($today, $activeStatuses) {
                $query->where('appointment_date', '<', $today)
                    ->orWhere(function ($query) use ($today, $activeStatuses) {
                        $query->where('appointment_date', '>', $today)
                            ->whereNotIn('status', $activeStatuses);
                    })
                    ->orWhere(function ($query) use ($today) {
                        $query->where('appointment_date', $today)
                            ->where('status', Appointment::STATUS_CANCELLED);
                    });
            })
                ->orderByDesc('appointment_date')
                ->orderByDesc('start_time');
        }

        return DentistAppointmentResource::collection($query->get());
    }

    public function update(UpdateDentistAppointmentRequest $request, Appointment $appointment)
    {
        // 1. Authorization: is this one of the logged-in dentist's own appointments? (AppointmentPolicy)
        Gate::authorize('manageAsDentist', $appointment);

        $data = $request->validated();

        if ($data === []) {
            throw ValidationException::withMessages([
                'status' => 'Send a new status, some notes, or both.',
            ]);
        }

        // 2. Business rule: is this status change allowed right now?
        if (isset($data['status']) && $data['status'] !== $appointment->status) {
            $error = $appointment->statusChangeError($data['status']);

            if ($error) {
                throw ValidationException::withMessages(['status' => $error]);
            }

            $appointment->status = $data['status']; // status is not mass-assignable
        }

        if (array_key_exists('dentist_notes', $data)) {
            $appointment->dentist_notes = $data['dentist_notes']; // not mass-assignable either
        }

        $appointment->save();
        $appointment->load(['patient.user', 'service']);

        return new DentistAppointmentResource($appointment);
    }
}
