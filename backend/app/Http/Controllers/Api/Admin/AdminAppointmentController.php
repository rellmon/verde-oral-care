<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            $query->whereDate('appointment_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('appointment_date', '<=', $filters['date_to']);
        }

        $direction = $filters['sort'] ?? 'desc';
        $query->orderBy('appointment_date', $direction)->orderBy('start_time', $direction);

        // withQueryString() keeps the filters in the pagination links.
        return AdminAppointmentResource::collection($query->paginate(15)->withQueryString());
    }
}