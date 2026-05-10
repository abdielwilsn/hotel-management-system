<?php

namespace App\Http\Requests\Inventory;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInventoryCategoryRequest extends FormRequest
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
        $categoryId = $this->route('inventory_category')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_categories', 'name')
                    ->ignore($categoryId)
                    ->where(fn ($query) => $query->where('team_id', $team->id)),
            ],
            'type' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:300'],
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
