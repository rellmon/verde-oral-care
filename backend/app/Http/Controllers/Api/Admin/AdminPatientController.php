<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPatientResource;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

class AdminPatientController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $query = Patient::with('user')
            ->withCount('appointments')
            // Sort alphabetically by the patient's name, which lives on the users table.
            ->orderBy(User::select('name')->whereColumn('users.id', 'patients.user_id'));

        $term = trim($filters['search'] ?? '');

        if ($term !== '') {
            // In a LIKE search, % and _ are wildcards. Escape them so they match literally.
            $like = '%'.addcslashes($term, '%_\\').'%';

            $query->whereHas('user', function ($query) use ($like) {
                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            });
        }

        // withQueryString() keeps the search term in the pagination links.
        return AdminPatientResource::collection($query->paginate(15)->withQueryString());
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'user',
            'appointments' => fn ($query) => $query
                ->with(['dentist.user', 'service'])
                ->orderByDesc('appointment_date')
                ->orderByDesc('start_time'),
        ]);
        $patient->loadCount('appointments');

        return new AdminPatientResource($patient);
    }
}
