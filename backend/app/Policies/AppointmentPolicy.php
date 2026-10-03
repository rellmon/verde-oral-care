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

    // A dentist may only update appointments that belong to them.
    public function manageAsDentist(User $user, Appointment $appointment): bool
    {
        return $user->dentist !== null
            && (int) $appointment->dentist_id === (int) $user->dentist->id;
    }
}
