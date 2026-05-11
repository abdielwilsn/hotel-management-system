<?php

namespace App\Http\Requests\Bookings;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProcessBookingPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank_transfer,online,other'],
            'payment_date' => ['required', 'date'],
            'status' => ['required', 'in:completed'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
                $booking = $this->route('booking');

                if (! $team || ! $booking) {
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
                $submittedAmount = round((float) $this->input('amount', 0), 2);

                if ($outstandingBalance <= 0) {
                    $validator->errors()->add('amount', 'This booking invoice is already fully paid.');

                    return;
                }

                if ($submittedAmount - $outstandingBalance > 0.01) {
                    $validator->errors()->add(
                        'amount',
                        'Payment amount cannot exceed the outstanding booking balance.',
                    );
                }
            },
        ];
    }
}
