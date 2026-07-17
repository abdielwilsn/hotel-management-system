<?php

namespace App\Http\Requests\Rooms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');

        return [
            'room_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rooms', 'room_number')->where('team_id', $team->id)->ignore($this->route('room')?->id),
            ],
            'floor' => ['required', 'integer', 'min:1', 'max:99'],
            // Room types are curated per team, so the allowed values come from
            // the database rather than a hardcoded list.
            'room_type' => ['required', Rule::in($team->roomTypes()->pluck('slug'))],
            'capacity' => ['required', 'integer', 'min:1', 'max:10'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:available,occupied,maintenance,cleaning'],
            'description' => ['nullable', 'string', 'max:500'],
            'features' => ['nullable', 'array'],
        ];
    }
}
