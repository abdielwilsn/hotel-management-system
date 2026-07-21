<?php

namespace App\Http\Requests\Staffs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveStaffRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('staff', 'email')->where('team_id', $team->id)->ignore($this->route('staff')?->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'in:male,female,other'],
            'role' => ['required', 'in:receptionist,housekeeping,accountant,manager,admin'],
            'department_id' => [
                'required',
                'exists:departments,id',
                Rule::exists('departments', 'id')->where('team_id', $team->id),
            ],
            'employment_date' => ['required', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive,on_leave'],
            // Optional: a manager can set the login password directly (useful
            // when no mailer is configured). Left blank, the staff member is
            // emailed a reset link instead. Only applies when creating staff.
            'password' => $this->isMethod('POST')
                ? ['nullable', 'string', 'confirmed', Password::default()]
                : ['nullable'],
        ];
    }
}
