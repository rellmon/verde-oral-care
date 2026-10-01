<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminDentistResource;
use App\Models\Dentist;

class AdminDentistController extends Controller
{
    public function index()
    {
        $dentists = Dentist::with('user')
            ->orderBy('id')
            ->get();

        return AdminDentistResource::collection($dentists);
    }
}