<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePaymentRequest extends FormRequest
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
            'invoice_id' => [
                'required',
                Rule::exists('invoices', 'id')->where('team_id', $team->id),
            ],
            'payment_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('payments', 'payment_number')
                    ->where('team_id', $team->id)
                    ->ignore($this->route('payment')?->id),
            ],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank_transfer,online,other'],
            'status' => ['required', 'in:pending,completed,failed,refunded'],
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

                if (! $team) {
                    return;
                }

                $invoice = $team->invoices()->find($this->input('invoice_id'));

                if (! $invoice) {
                    return;
                }

                if ($this->input('status') !== 'completed') {
                    return;
                }

                $otherCompleted = Payment::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->when(
                        $this->route('payment'),
                        fn ($query) => $query->where('id', '!=', $this->route('payment')->id),
                    )
                    ->sum('amount');

                $outstandingBalance = round((float) $invoice->total_amount - (float) $otherCompleted, 2);
                $submittedAmount = round((float) $this->input('amount', 0), 2);

                if ($outstandingBalance <= 0) {
                    $validator->errors()->add('invoice_id', 'This invoice is already fully paid.');

                    return;
                }

                if (abs($submittedAmount - $outstandingBalance) > 0.01) {
                    $validator->errors()->add(
                        'amount',
                        'Partial payments are not accepted. Enter the full outstanding balance.',
                    );
                }
            },
        ];
    }
}
