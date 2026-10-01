<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DentistAppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            $query->whereDate('appointment_date', $today)
                ->where('status', '!=', Appointment::STATUS_CANCELLED)
                ->orderBy('start_time');
        } elseif ($type === 'upcoming') {
            $query->whereDate('appointment_date', '>', $today)
                ->whereIn('status', $activeStatuses)
                ->orderBy('appointment_date')
                ->orderBy('start_time');
        } else {
            $query->where(function ($query) use ($today, $activeStatuses) {
                $query->whereDate('appointment_date', '<', $today)
                    ->orWhere(function ($query) use ($today, $activeStatuses) {
                        $query->whereDate('appointment_date', '>', $today)
                            ->whereNotIn('status', $activeStatuses);
                    });
            })
                ->orderByDesc('appointment_date')
                ->orderByDesc('start_time');
        }

        return DentistAppointmentResource::collection($query->get());
    }
}