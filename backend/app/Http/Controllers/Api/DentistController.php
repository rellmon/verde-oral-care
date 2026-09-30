<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DentistResource;
use App\Models\Dentist;

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
}