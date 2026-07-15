<?php

namespace App\Http\Requests\Pos;

use App\Models\PosOutlet;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePosMenuItemRequest extends FormRequest
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
        /** @var PosOutlet $outlet */
        $outlet = $this->route('pos_outlet');
        $itemId = $this->route('pos_menu_item')?->id;

        return [
            'pos_category_id' => [
                'nullable',
                'integer',
                Rule::exists('pos_categories', 'id')
                    ->where(fn ($query) => $query->where('pos_outlet_id', $outlet->id)),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('pos_menu_items', 'name')
                    ->ignore($itemId)
                    ->where(fn ($query) => $query->where('pos_outlet_id', $outlet->id)),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['required', 'string', 'max:40'],
            'track_stock' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Team $team, PosOutlet $outlet): array
    {
        return [
            ...$this->validated(),
            'team_id' => $team->id,
            'pos_outlet_id' => $outlet->id,
        ];
    }
}
