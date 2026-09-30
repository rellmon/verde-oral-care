<?php

use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json([
        'message' => 'Verde Oral Care API is running',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::get('/services', [ServiceController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/admin/ping', fn () => response()->json(['message' => 'Hello, admin']))
        ->middleware('role:admin');

    Route::get('/dentist/ping', fn () => response()->json(['message' => 'Hello, dentist or admin']))
        ->middleware('role:dentist,admin');

    Route::get('/patient/ping', fn () => response()->json(['message' => 'Hello, patient']))
        ->middleware('role:patient');
});