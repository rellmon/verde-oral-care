<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    // A patient may only cancel their own appointments.
    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->patient !== null
            && (int) $appointment->patient_id === (int) $user->patient->id;
    }
}