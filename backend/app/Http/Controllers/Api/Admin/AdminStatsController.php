<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function index()
    {
        $today = Carbon::today()->toDateString();
        $weekEnd = Carbon::today()->addDays(7)->toDateString();
        $activeStatuses = [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED];

        // One grouped query for the status breakdown, e.g. ['pending' => 4, 'confirmed' => 2].
        $countsByStatus = Appointment::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Make sure every status appears, even when there are no appointments with it yet.
        $byStatus = [];
        foreach (array_keys(Appointment::STATUS_TRANSITIONS) as $status) {
            $byStatus[$status] = (int) $countsByStatus->get($status, 0);
        }

        return response()->json([
            'data' => [
                'appointments_today' => Appointment::whereDate('appointment_date', $today)
                    ->where('status', '!=', Appointment::STATUS_CANCELLED)
                    ->count(),
                'pending_confirmation' => Appointment::where('status', Appointment::STATUS_PENDING)
                    ->whereDate('appointment_date', '>=', $today)
                    ->count(),
                'upcoming_week' => Appointment::whereDate('appointment_date', '>', $today)
                    ->whereDate('appointment_date', '<=', $weekEnd)
                    ->whereIn('status', $activeStatuses)
                    ->count(),
                'total_patients' => Patient::count(),
                'active_dentists' => Dentist::where('is_active', true)->count(),
                'active_services' => Service::where('is_active', true)->count(),
                'appointments_by_status' => $byStatus,
            ],
        ]);
    }
}