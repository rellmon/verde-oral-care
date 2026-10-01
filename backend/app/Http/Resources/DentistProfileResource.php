<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentistProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'license_number' => $this->license_number,
            'specialization' => $this->specialization,
            'biography' => $this->biography,
            'profile_image' => $this->profile_image,
            'is_active' => $this->is_active,
            'schedules' => DentistScheduleResource::collection($this->whenLoaded('schedules')),
        ];
    }
}