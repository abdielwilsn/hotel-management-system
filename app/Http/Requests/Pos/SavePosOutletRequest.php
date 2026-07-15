<?php

namespace App\Http\Requests\Pos;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePosOutletRequest extends FormRequest
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
        $outletId = $this->route('pos_outlet')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('pos_outlets', 'name')
                    ->ignore($outletId)
                    ->where(fn ($query) => $query->where('team_id', $team->id)),
            ],
            'type' => ['required', Rule::in(['bar', 'restaurant'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
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
