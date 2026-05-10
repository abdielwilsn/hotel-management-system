<?php

namespace App\Http\Requests\Inventory;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');
        $itemId = $this->route('inventory_item')?->id;

        return [
            'inventory_category_id' => [
                'required',
                'integer',
                Rule::exists('inventory_categories', 'id')
                    ->where(fn ($query) => $query->where('team_id', $team->id)),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('inventory_items', 'name')
                    ->ignore($itemId)
                    ->where(fn ($query) => $query
                        ->where('team_id', $team->id)
                        ->where('inventory_category_id', (int) $this->input('inventory_category_id'))),
            ],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['required', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Team $team): array
    {
        return [
            ...$this->validated(),
            'team_id' => $team->id,
        ];
    }
}
