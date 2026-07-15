<?php

namespace App\Http\Requests\Bookings;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBookingDiscountRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $value = (float) $this->input('value');

                if ($this->input('type') === 'percentage' && $value > 100) {
                    $validator->errors()->add('value', 'A percentage discount cannot exceed 100%.');

                    return;
                }

                /** @var Booking|null $booking */
                $booking = $this->route('booking');

                if ($this->input('type') === 'fixed' && $booking && $value > (float) $booking->total_amount) {
                    $validator->errors()->add('value', 'The discount cannot exceed the booking total.');
                }
            },
        ];
    }
}
