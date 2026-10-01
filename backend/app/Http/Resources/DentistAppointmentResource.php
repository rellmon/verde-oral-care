<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentistAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_date' => $this->appointment_date->toDateString(),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'reason' => $this->reason,
            'status' => $this->status,
            'dentist_notes' => $this->dentist_notes,
            'available_statuses' => $this->availableStatuses(),
            // Only what the dentist needs to see: no email, address or emergency contact.
            'patient' => $this->whenLoaded('patient', fn () => [
                'name' => $this->patient->user->name,
                'phone' => $this->patient->user->phone,
                'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
            ]),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'duration_minutes' => $this->service->duration_minutes,
            ]),
        ];
    }
}