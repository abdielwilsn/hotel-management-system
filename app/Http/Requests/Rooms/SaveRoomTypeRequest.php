<?php

namespace App\Http\Requests\Rooms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomTypeRequest extends FormRequest
{
    /**
     * Route middleware already restricts this to managers.
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
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('room_types', 'name')
                    ->where('team_id', $team->id)
                    ->ignore($this->route('room_type')?->id),
            ],
        ];
    }
}
