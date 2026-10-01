<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentistScheduleResource extends JsonResource
{
    private const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week,
            'day_name' => self::DAY_NAMES[$this->day_of_week],
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
        ];
    }
}