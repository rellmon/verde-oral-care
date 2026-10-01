<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentistAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware (auth:sanctum + role:dentist) and AppointmentPolicy restrict who gets here.
        return true;
    }

    public function rules(): array
    {
        return [
            // 'pending' is not listed: an appointment can never go back to pending.
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    Appointment::STATUS_CONFIRMED,
                    Appointment::STATUS_COMPLETED,
                    Appointment::STATUS_NO_SHOW,
                    Appointment::STATUS_CANCELLED,
                ]),
            ],
            'dentist_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}