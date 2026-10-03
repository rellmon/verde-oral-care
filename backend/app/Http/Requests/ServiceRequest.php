<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins can reach these routes; the role:admin middleware enforces that.
        return true;
    }

    public function rules(): array
    {
        // On update this is the Service being edited; on create it is null.
        $service = $this->route('service');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->ignore($service)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
