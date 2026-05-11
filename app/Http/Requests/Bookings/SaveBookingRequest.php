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
            'status' => ['required', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
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

                        // Check for overlapping bookings
                        $checkInDate = Carbon::parse((string) $this->input('check_in_date'));
                        $checkOutDate = Carbon::parse((string) $this->input('check_out_date'));
                        $bookingId = $this->route('booking')?->id;

                        $overlap = Booking::query()
                            ->where('room_id', $room->id)
                            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                            ->where(function ($query) use ($checkInDate, $checkOutDate): void {
                                $query->whereBetween('check_in_date', [$checkInDate, $checkOutDate->subDay()])
                                    ->orWhereBetween('check_out_date', [$checkInDate->addDay(), $checkOutDate])
                                    ->orWhere(function ($q) use ($checkInDate, $checkOutDate): void {
                                        $q->where('check_in_date', '<=', $checkInDate)
                                            ->where('check_out_date', '>=', $checkOutDate);
                                    });
                            });

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
