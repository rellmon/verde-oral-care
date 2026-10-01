<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDentistScheduleRequest;
use App\Http\Resources\DentistScheduleResource;
use App\Models\Dentist;
use Illuminate\Support\Facades\DB;

class AdminDentistScheduleController extends Controller
{
    public function index(Dentist $dentist)
    {
        $schedules = $dentist->schedules()->orderBy('day_of_week')->get();

        return DentistScheduleResource::collection($schedules);
    }

    public function update(AdminDentistScheduleRequest $request, Dentist $dentist)
    {
        $schedules = $request->validated()['schedules'];

        DB::transaction(function () use ($dentist, $schedules) {
            $dentist->schedules()->delete();

            foreach ($schedules as $schedule) {
                $dentist->schedules()->create([
                    'day_of_week' => $schedule['day_of_week'],
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                ]);
            }
        });

        return $this->index($dentist);
    }
}