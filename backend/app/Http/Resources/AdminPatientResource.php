<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'appointments_count' => $this->whenCounted('appointments'),

            // The sensitive details are only included on the detail view,
            // which is the only place that loads the appointments.
            $this->mergeWhen($this->relationLoaded('appointments'), fn () => [
                'date_of_birth' => $this->date_of_birth?->toDateString(),
                'gender' => $this->gender,
                'address' => $this->address,
                'emergency_contact_name' => $this->emergency_contact_name,
                'emergency_contact_phone' => $this->emergency_contact_phone,
                'appointments' => AdminAppointmentResource::collection($this->appointments),
            ]),
        ];
    }
}
