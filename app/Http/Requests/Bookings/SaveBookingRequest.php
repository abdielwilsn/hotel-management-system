<?php

namespace App\Http\Requests\Bookings;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Treat blank discount fields (e.g. the "No discount" option) as absent.
        if (blank($this->input('discount_type'))) {
            $this->merge(['discount_type' => null, 'discount_value' => null]);
        }
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
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where('team_id', $team->id),
            ],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:20'],
            'number_of_guests' => ['required', 'integer', 'min:1', 'max:20'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            // The time of day decides how many nights this is; without it an
            // 08:00 arrival cannot be told from a 05:00 one.
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date', 'after:check_in_at'],
            'status' => ['required', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['nullable', 'required_with:discount_value', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'required_with:discount_type', 'numeric', 'gt:0', 'max:99999999.99'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'process_payment' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'in:cash,card,bank_transfer,online,other'],
            'payment_date' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->route('current_team');

                if ($team && $this->filled('room_id') && $this->filled('check_in_date') && $this->filled('check_out_date')) {
                    $room = Room::query()
                        ->where('team_id', $team->id)
                        ->find($this->input('room_id'));

                    if ($room) {
                        $nights = max(
                            1,
                            Carbon::parse((string) $this->input('check_in_date'))
                                ->diffInDays(Carbon::parse((string) $this->input('check_out_date'))),
                        );

                        $calculatedTotal = round(((float) $room->price_per_night) * $nights, 2);

                        if ($calculatedTotal > 99999999.99) {
                            $validator->errors()->add(
                                'check_out_date',
                                'Stay duration is too long for the selected room rate. Please reduce the date range.',
                            );
                        }

                        // Check for overlapping bookings. Shares the Booking::overlapping()
                        // scope with the room availability lookup so the room picker and
                        // this validation can never disagree.
                        $bookingId = $this->route('booking')?->id;

                        $overlap = Booking::query()
                            ->where('room_id', $room->id)
                            ->overlapping(
                                (string) $this->input('check_in_date'),
                                (string) $this->input('check_out_date'),
                            );

                        if ($bookingId) {
                            $overlap->where('id', '!=', $bookingId);
                        }

                        if ($overlap->exists()) {
                            $validator->errors()->add(
                                'room_id',
                                'This room is already booked for the selected dates.',
                            );
                        }
                    }
                }

                if ($this->filled('discount_type') && $this->filled('discount_value')) {
                    $discountValue = (float) $this->input('discount_value');

                    if ($this->input('discount_type') === 'percentage' && $discountValue > 100) {
                        $validator->errors()->add('discount_value', 'A percentage discount cannot exceed 100%.');
                    }

                    if ($this->input('discount_type') === 'fixed' && isset($calculatedTotal) && $discountValue > $calculatedTotal) {
                        $validator->errors()->add('discount_value', 'The discount cannot exceed the booking total.');
                    }
                }

                if (! $this->boolean('process_payment')) {
                    return;
                }

                if (! $this->filled('payment_amount')) {
                    $validator->errors()->add('payment_amount', 'Payment amount is required when processing payment.');
                }

                if (! $this->filled('payment_method')) {
                    $validator->errors()->add('payment_method', 'Payment method is required when processing payment.');
                }

                if (! $this->filled('payment_date')) {
                    $validator->errors()->add('payment_date', 'Payment date is required when processing payment.');
                }

                if ($team && $this->filled('room_id') && $this->filled('check_in_date') && $this->filled('check_out_date') && $this->filled('payment_amount')) {
                    $room = Room::query()
                        ->where('team_id', $team->id)
                        ->find($this->input('room_id'));

                    if (! $room) {
                        return;
                    }

                    $nights = max(
                        1,
                        Carbon::parse((string) $this->input('check_in_date'))
                            ->diffInDays(Carbon::parse((string) $this->input('check_out_date'))),
                    );

                    $bookingTotal = round(((float) $room->price_per_night) * $nights, 2);
                    $submittedAmount = round((float) $this->input('payment_amount'), 2);

                    if ($submittedAmount - $bookingTotal > 0.01) {
                        $validator->errors()->add(
                            'payment_amount',
                            'Payment amount cannot exceed the booking total.',
                        );
                    }
                }
            },
        ];
    }
}
