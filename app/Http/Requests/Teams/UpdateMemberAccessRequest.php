<?php

namespace App\Http\Requests\Teams;

use App\Enums\DataScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberAccessRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'data_scope' => ['required', Rule::enum(DataScope::class)],
            'department_ids' => ['present', 'array'],
            'department_ids.*' => [
                Rule::exists('departments', 'id')->where('team_id', $team?->id),
            ],
        ];
    }
}
