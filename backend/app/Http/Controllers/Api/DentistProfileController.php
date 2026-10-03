<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DentistProfileResource;
use Illuminate\Http\Request;

class DentistProfileController extends Controller
{
    public function show(Request $request)
    {
        // The dentist comes from the logged-in user, never from the URL.
        $dentist = $request->user()->dentist;
        abort_unless($dentist, 403, 'Your account has no dentist profile.');

        $dentist->load([
            'user',
            'schedules' => fn ($query) => $query->orderBy('day_of_week'),
        ]);

        return new DentistProfileResource($dentist);
    }
}
