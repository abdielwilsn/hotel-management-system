<?php

namespace App\Http\Requests\Bookings;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckoutBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settlement_amount' => ['nullable', 'numeric', 'min:0.01'],
            'settlement_method' => ['nullable', 'in:cash,card,bank_transfer,online,other'],
            'settlement_payment_date' => ['nullable', 'date'],
            'settlement_reference' => ['nullable', 'string', 'max:100'],
            'settlement_notes' => ['nullable', 'string', 'max:1000'],
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

                if ($booking->status !== 'checked_in') {
                    $validator->errors()->add('settlement_amount', 'Only checked-in bookings can be checked out.');

                    return;
                }

                $invoice = Invoice::query()
                    ->where('team_id', $team->id)
                    ->where('booking_id', $booking->id)
                    ->latest('id')
                    ->first();

                if (! $invoice) {
                    return;
                }

                $outstandingBalance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
                $submittedAmount = round((float) ($this->input('settlement_amount') ?? 0), 2);

                if ($outstandingBalance <= 0) {
                    return;
                }

                if (! $this->filled('settlement_amount')) {
                    $validator->errors()->add('settlement_amount', 'The remaining balance must be settled before checkout.');
                }

                if (! $this->filled('settlement_method')) {
                    $validator->errors()->add('settlement_method', 'Payment method is required to settle the remaining balance.');
                }

                if (! $this->filled('settlement_payment_date')) {
                    $validator->errors()->add('settlement_payment_date', 'Payment date is required to settle the remaining balance.');
                }

                if (abs($submittedAmount - $outstandingBalance) > 0.01) {
                    $validator->errors()->add('settlement_amount', 'Checkout requires the full remaining balance.');
                }
            },
        ];
    }
}
