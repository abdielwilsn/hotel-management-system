<?php

namespace App\Http\Requests\Pos;

use App\Models\PosOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePosStockRequest extends FormRequest
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

        return [
            'pos_menu_item_id' => [
                'required',
                'integer',
                Rule::exists('pos_menu_items', 'id')
                    ->where(fn ($query) => $query->where('pos_outlet_id', $outlet->id)),
            ],
            'business_date' => ['required', 'date'],
            'opening_stock' => ['required', 'integer', 'min:0'],
            'new_stock' => ['required', 'integer', 'min:0'],
            'closing_stock' => ['required', 'integer', 'min:0'],
            'damaged' => ['required', 'integer', 'min:0'],
            'recorded_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_closed' => ['required', 'boolean'],
        ];
    }
}
