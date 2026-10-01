<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminDentistScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware (auth:sanctum + role:admin) already restricts who gets here.
        return true;
    }

    public function rules(): array
    {
        return [
            // 'present' allows an empty list, which means the dentist has no working days.
            'schedules' => ['present', 'array', 'max:7'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
        ];
    }
}