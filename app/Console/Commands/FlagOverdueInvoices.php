<?php

namespace App\Console\Commands;

use App\Enums\Ability;
use App\Models\Invoice;
use App\Notifications\Invoices\InvoiceOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Nothing in this app ever moved an invoice to "overdue" once its due date
 * passed — it just sat as "issued"/"partially_paid" forever. This is what
 * actually detects that, then tells whoever manages invoices about it.
 */
class FlagOverdueInvoices extends Command
{
    protected $signature = 'invoices:flag-overdue';

    protected $description = 'Mark invoices overdue once their due date passes and notify managers';

    public function handle(): int
    {
        $invoices = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->with('team')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices have gone overdue.');

            return self::SUCCESS;
        }

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'overdue']);

            if ($invoice->team === null) {
                continue;
            }

            Notification::send(
                $invoice->team->membersWithAbility(Ability::ManageInvoices),
                new InvoiceOverdue($invoice),
            );
        }

        $this->info("Flagged {$invoices->count()} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
