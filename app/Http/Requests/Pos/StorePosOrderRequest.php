<?php

namespace App\Http\Requests\Pos;

use App\Models\PosOutlet;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosOrderRequest extends FormRequest
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
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'charge_type' => ['required', Rule::in(['walk_in', 'room'])],
            'payment_mode' => ['required', Rule::in(['cash', 'card', 'transfer', 'room'])],
            'served_by' => ['nullable', 'string', 'max:120'],

            'booking_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('charge_type') === 'room'),
                'integer',
                Rule::exists('bookings', 'id')
                    ->where(fn ($query) => $query
                        ->where('team_id', $team->id)
                        ->whereIn('status', ['pending', 'confirmed', 'checked_in'])),
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_menu_item_id' => [
                'required',
                'integer',
                Rule::exists('pos_menu_items', 'id')
                    ->where(fn ($query) => $query
                        ->where('pos_outlet_id', $outlet->id)
                        ->where('is_active', true)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('charge_type') === 'room') {
            $this->merge(['payment_mode' => 'room']);
        }
    }
}
