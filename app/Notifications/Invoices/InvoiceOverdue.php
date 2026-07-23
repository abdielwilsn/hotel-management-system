<?php

namespace App\Notifications\Invoices;

use App\Models\Invoice;
use App\Notifications\TeamNotification;

class InvoiceOverdue extends TeamNotification
{
    public function __construct(public Invoice $invoice)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, invoice_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $balance = number_format(max((float) $this->invoice->total_amount - (float) $this->invoice->paid_amount, 0), 2);

        return [
            'team_id' => $this->invoice->team_id,
            'message' => "Invoice {$this->invoice->invoice_number} for {$this->invoice->guest_name} is overdue — {$balance} outstanding.",
            'url' => route('invoices.index', $this->invoice->team->slug),
            'invoice_id' => $this->invoice->id,
        ];
    }
}
