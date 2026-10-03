<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDentistRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware (auth:sanctum + role:admin) already restricts who gets here.
        return true;
    }

    public function rules(): array
    {
        // On update, the route has a {dentist} parameter (already loaded as a model).
        // On create, there is none, so this is null.
        $dentist = $this->route('dentist');
        $isUpdate = $dentist !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($dentist?->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => $isUpdate
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8'],
            'license_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('dentists', 'license_number')->ignore($dentist?->id),
            ],
            'specialization' => ['required', 'string', 'max:255'],
            'biography' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
