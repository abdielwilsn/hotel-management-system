<?php

namespace App\Http\Requests\Pos;

use App\Models\PosOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePosStockRequest extends FormRequest
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
                    ->where(fn ($query) => $query
                        ->where('pos_outlet_id', $outlet->id)
                        ->where('track_stock', true)),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'business_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
