<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'specialization' => $this->specialization,
            'biography' => $this->biography,
            'profile_image' => $this->profile_image,
            'schedules' => DentistScheduleResource::collection($this->whenLoaded('schedules')),
        ];
    }
}