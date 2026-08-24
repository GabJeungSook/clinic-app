<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('services.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'service_category_id' => ['nullable', 'string', 'exists:service_categories,id'],
            'description' => ['nullable', 'string'],
            'default_session_count' => ['required', 'integer', 'min:1', 'max:100'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'default_interval_days' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            // Optional bill of materials, supplied when creating a service.
            'consumables' => ['nullable', 'array'],
            'consumables.*.inventory_item_id' => ['required', 'string', 'exists:inventory_items,id'],
            'consumables.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'consumables.*.unit_id' => ['required', 'string', 'exists:units,id'],
        ];
    }
}
