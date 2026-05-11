<?php

namespace App\Http\Requests\Bookings;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class ExtendBookingStayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_out_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->route('current_team');
                $booking = $this->route('booking');

                if (! $team || ! $booking) {
                    return;
                }

                if (! in_array($booking->status, ['confirmed', 'checked_in'], true)) {
                    $validator->errors()->add('check_out_date', 'Only confirmed or checked-in bookings can be extended.');

                    return;
                }

                $newCheckOutDate = Carbon::parse((string) $this->input('check_out_date'));

                if (! $newCheckOutDate->greaterThan($booking->check_out_date)) {
                    $validator->errors()->add('check_out_date', 'The new check-out date must be after the current check-out date.');

                    return;
                }

                $overlap = Booking::query()
                    ->where('room_id', $booking->room_id)
                    ->where('id', '!=', $booking->id)
                    ->where(function (Builder $query): void {
                        $query->where('status', 'pending')
                            ->orWhere('status', 'confirmed')
                            ->orWhere('status', 'checked_in');
                    })
                    ->where(function (Builder $query) use ($booking, $newCheckOutDate): void {
                        $query->whereBetween('check_in_date', [$booking->check_in_date->toDateString(), $newCheckOutDate->copy()->subDay()->toDateString()])
                            ->orWhereBetween('check_out_date', [$booking->check_in_date->copy()->addDay()->toDateString(), $newCheckOutDate->toDateString()])
                            ->orWhere(function (Builder $nestedQuery) use ($booking, $newCheckOutDate): void {
                                $nestedQuery->where('check_in_date', '<=', $booking->check_in_date)
                                    ->where('check_out_date', '>=', $newCheckOutDate);
                            });
                    })
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('check_out_date', 'The room already has another active booking in the extension period.');
                }
            },
        ];
    }
}
