<?php

namespace App\Http\Requests\Expenses;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveExpenseRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:utilities,maintenance,supplies,payroll,marketing,other'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'incurred_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:pending,paid,cancelled'],
            'description' => ['nullable', 'string', 'max:2000'],
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
