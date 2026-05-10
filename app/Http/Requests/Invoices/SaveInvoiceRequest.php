<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveInvoiceRequest extends FormRequest
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
            'booking_id' => [
                'nullable',
                Rule::exists('bookings', 'id')->where('team_id', $team->id),
            ],
            'invoice_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('invoices', 'invoice_number')
                    ->where('team_id', $team->id)
                    ->ignore($this->route('invoice')?->id),
            ],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,issued,partially_paid,paid,overdue,void'],
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
                $subtotal = (float) $this->input('subtotal', 0);
                $tax = (float) $this->input('tax_amount', 0);
                $discount = (float) $this->input('discount_amount', 0);
                $paid = (float) $this->input('paid_amount', 0);
                $total = round($subtotal + $tax - $discount, 2);

                if ($total < 0) {
                    $validator->errors()->add('discount_amount', 'Discount cannot exceed subtotal plus tax.');
                }

                if ($paid > $total) {
                    $validator->errors()->add('paid_amount', 'Paid amount cannot exceed invoice total.');
                }
            },
        ];
    }
}
