<?php

namespace App\Http\Requests\Guests;

use App\Models\Team;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveGuestRequest extends FormRequest
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
        /** @var Team $team */
        $team = $this->route('current_team');
        $guestId = $this->route('guest')?->id;

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
                Rule::unique('guests', 'email')
                    ->ignore($guestId)
                    ->where(fn ($query) => $query->where('team_id', $team->id)),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'loyalty_tier' => ['required', 'in:standard,silver,gold,platinum'],
            'loyalty_points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'last_stay_date' => ['nullable', 'date'],
            'preferences' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
