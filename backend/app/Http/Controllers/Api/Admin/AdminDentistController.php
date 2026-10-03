<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDentistRequest;
use App\Http\Resources\AdminDentistResource;
use App\Models\Dentist;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminDentistController extends Controller
{
    public function index()
    {
        $dentists = Dentist::with('user')
            ->orderBy('id')
            ->get();

        return AdminDentistResource::collection($dentists);
    }

    public function store(AdminDentistRequest $request)
    {
        $data = $request->validated();

        $dentist = DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
            ]);
            $user->role = 'dentist'; // role is not mass-assignable, so we set it explicitly
            $user->save();

            return Dentist::create([
                'user_id' => $user->id,
                'license_number' => $data['license_number'],
                'specialization' => $data['specialization'],
                'biography' => $data['biography'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        $dentist->load('user');

        return (new AdminDentistResource($dentist))
            ->response()
            ->setStatusCode(201);
    }

    public function update(AdminDentistRequest $request, Dentist $dentist)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $dentist) {
            $user = $dentist->user;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $dentist->update([
                'license_number' => $data['license_number'],
                'specialization' => $data['specialization'],
                'biography' => $data['biography'] ?? null,
                'is_active' => $data['is_active'] ?? $dentist->is_active,
            ]);
        });

        $dentist->load('user');

        return new AdminDentistResource($dentist);
    }
}
