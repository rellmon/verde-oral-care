<?php

use App\Http\Controllers\Api\Admin\AdminDentistController;
use App\Http\Controllers\Api\DentistController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminServiceController;
use App\Http\Controllers\Api\Admin\AdminDentistScheduleController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientProfileController;
use App\Http\Controllers\Api\DentistAppointmentController;
use App\Http\Controllers\Api\DentistProfileController;
use App\Http\Controllers\Api\Admin\AdminStatsController;
use App\Http\Controllers\Api\Admin\AdminAppointmentController;
use App\Http\Controllers\Api\Admin\AdminPatientController;

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

Route::get('/dentists', [DentistController::class, 'index']);

Route::get('/dentists/{dentist}', [DentistController::class, 'show']);

Route::get('/dentists/{dentist}/availability', [DentistController::class, 'availability']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/patient/profile', [PatientProfileController::class, 'show']);
    Route::put('/patient/profile', [PatientProfileController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'role:dentist'])->group(function () {
    Route::get('/dentist/appointments', [DentistAppointmentController::class, 'index']);
    Route::patch('/dentist/appointments/{appointment}', [DentistAppointmentController::class, 'update']);
    Route::get('/dentist/profile', [DentistProfileController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/services', [AdminServiceController::class, 'index']);
        Route::post('/services', [AdminServiceController::class, 'store']);
        Route::put('/services/{service}', [AdminServiceController::class, 'update']);
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy']);
        Route::get('/dentists', [AdminDentistController::class, 'index']);
        Route::post('/dentists', [AdminDentistController::class, 'store']);
        Route::put('/dentists/{dentist}', [AdminDentistController::class, 'update']);
        Route::get('/dentists/{dentist}/schedules', [AdminDentistScheduleController::class, 'index']);
        Route::put('/dentists/{dentist}/schedules', [AdminDentistScheduleController::class, 'update']);
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::get('/appointments', [AdminAppointmentController::class, 'index']);
        Route::patch('/appointments/{appointment}/status', [AdminAppointmentController::class, 'updateStatus']);
        Route::get('/appointments/{appointment}/slots', [AdminAppointmentController::class, 'slots']);
        Route::patch('/appointments/{appointment}/reschedule', [AdminAppointmentController::class, 'reschedule']);
        Route::get('/patients', [AdminPatientController::class, 'index']);
        Route::get('/patients/{patient}', [AdminPatientController::class, 'show']);
});