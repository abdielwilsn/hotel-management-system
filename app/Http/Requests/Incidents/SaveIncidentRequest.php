<?php

namespace App\Http\Requests\Incidents;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveIncidentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            // Scoped to the team, and narrowed to the reporter's own
            // departments in the controller.
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where('team_id', $team->id),
            ],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(IncidentCategory::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'room_id' => [
                'nullable',
                Rule::exists('rooms', 'id')->where('team_id', $team->id),
            ],
            'booking_id' => [
                'nullable',
                Rule::exists('bookings', 'id')->where('team_id', $team->id),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'occurred_at.before_or_equal' => 'An incident cannot be reported before it happens.',
        ];
    }
}
