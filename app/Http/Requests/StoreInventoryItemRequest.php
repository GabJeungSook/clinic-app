<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $itemId = $this->route('inventory')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('inventory_items', 'sku')
                    ->where('branch_id', $this->user()?->branch_id)
                    ->ignore($itemId),
            ],
            'barcode' => ['nullable', 'string', 'max:60'],
            'type' => ['required', new Enum(ItemType::class)],
            'inventory_category_id' => ['nullable', 'string', 'exists:inventory_categories,id'],
            'new_category' => ['nullable', 'string', 'max:100'],
            'base_unit_id' => ['required', 'string', 'exists:units,id'],
            'is_batch_tracked' => ['boolean'],
            'track_expiry' => ['boolean'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0'],
            'default_sell_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            // Optional opening stock (only used on create).
            'opening_qty' => ['nullable', 'numeric', 'min:0'],
            'opening_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_expiry' => ['nullable', 'date'],
        ];
    }
}
