<?php

namespace App\Http\Requests;

use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('patients.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('patients', 'code')
                    ->where('branch_id', $this->user()?->branch_id)
                    ->ignore($patientId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'sex' => ['nullable', new Enum(Sex::class)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
