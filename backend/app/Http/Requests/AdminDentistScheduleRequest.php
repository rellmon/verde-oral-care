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

    // Plain-language messages. The * stands for any position in the list of days.
    public function messages(): array
    {
        return [
            'schedules.present' => 'The list of working days is missing.',
            'schedules.max' => 'A week has at most seven days.',
            'schedules.*.day_of_week.required' => 'Choose a day.',
            'schedules.*.day_of_week.between' => 'Choose a day from Sunday to Saturday.',
            'schedules.*.day_of_week.distinct' => 'Each day can only be listed once.',
            'schedules.*.start_time.required' => 'Enter an opening time.',
            'schedules.*.start_time.date_format' => 'Enter the opening time as hours and minutes, for example 09:00.',
            'schedules.*.end_time.required' => 'Enter a closing time.',
            'schedules.*.end_time.date_format' => 'Enter the closing time as hours and minutes, for example 17:00.',
            'schedules.*.end_time.after' => 'The closing time must be later than the opening time.',
        ];
    }
}