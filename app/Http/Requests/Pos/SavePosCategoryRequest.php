<?php

namespace App\Http\Requests\Pos;

use App\Models\PosOutlet;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePosCategoryRequest extends FormRequest
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
        $categoryId = $this->route('pos_category')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('pos_categories', 'name')
                    ->ignore($categoryId)
                    ->where(fn ($query) => $query->where('pos_outlet_id', $outlet->id)),
            ],
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
