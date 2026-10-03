<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Resources\DentistResource;
use App\Models\Dentist;
use App\Models\Service;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

class DentistController extends Controller
{
    public function index()
    {
        $dentists = Dentist::with('user')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return DentistResource::collection($dentists);
    }

    public function show(Dentist $dentist)
    {
        abort_unless($dentist->is_active, 404);

        $dentist->load([
            'user',
            'schedules' => fn ($query) => $query->orderBy('day_of_week'),
        ]);

        return new DentistResource($dentist);
    }

    public function availability(AvailabilityRequest $request, Dentist $dentist, AvailabilityService $availability)
    {
        abort_unless($dentist->is_active, 404);

        $service = Service::findOrFail($request->validated('service_id'));
        $date = Carbon::createFromFormat('Y-m-d', $request->validated('date'))->startOfDay();

        return response()->json([
            'data' => $availability->getAvailableSlots($dentist, $service, $date),
        ]);
    }
}
