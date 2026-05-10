<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\SavePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Payment::class, $current_team]);

        $payments = $current_team->payments()
            ->with('invoice:id,invoice_number,guest_name,total_amount,paid_amount')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $invoices = $current_team->invoices()
            ->orderByDesc('issue_date')
            ->get(['id', 'invoice_number', 'guest_name', 'total_amount', 'paid_amount', 'status']);

        $methods = ['cash', 'card', 'bank_transfer', 'online', 'other'];
        $statuses = ['pending', 'completed', 'failed', 'refunded'];

        return Inertia::render('payments/Index', [
            'payments' => $payments,
            'invoices' => $invoices,
            'methods' => $methods,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SavePaymentRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Payment::class, $current_team]);

        $data = $request->validated();
        $data['payment_number'] = $this->generatePaymentNumber($current_team);

        $payment = $current_team->payments()->create($data);

        $this->refreshInvoicePaidAmount($payment->invoice);

        return redirect()->route('payments.index', $current_team->slug)
            ->with('message', "Payment {$payment->payment_number} has been recorded.");
    }

    public function edit(Request $request, Team $current_team, Payment $payment): Response
    {
        $this->paymentForTeam($current_team, $payment);

        Gate::authorize('update', [$payment, $current_team]);

        $invoices = $current_team->invoices()
            ->orderByDesc('issue_date')
            ->get(['id', 'invoice_number', 'guest_name', 'total_amount', 'paid_amount', 'status']);

        $methods = ['cash', 'card', 'bank_transfer', 'online', 'other'];
        $statuses = ['pending', 'completed', 'failed', 'refunded'];

        return Inertia::render('payments/Edit', [
            'payment' => $payment->load('invoice:id,invoice_number,guest_name,total_amount,paid_amount'),
            'invoices' => $invoices,
            'methods' => $methods,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SavePaymentRequest $request, Team $current_team, Payment $payment): RedirectResponse
    {
        $this->paymentForTeam($current_team, $payment);

        Gate::authorize('update', [$payment, $current_team]);

        $previousInvoiceId = $payment->invoice_id;

        $payment->update($request->validated());

        if ((int) $previousInvoiceId !== (int) $payment->invoice_id) {
            $previousInvoice = Invoice::query()->find($previousInvoiceId);

            if ($previousInvoice) {
                $this->refreshInvoicePaidAmount($previousInvoice);
            }
        }

        $this->refreshInvoicePaidAmount($payment->invoice);

        return redirect()->route('payments.index', $current_team->slug)
            ->with('message', "Payment {$payment->payment_number} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Payment $payment): RedirectResponse
    {
        $this->paymentForTeam($current_team, $payment);

        Gate::authorize('delete', [$payment, $current_team]);

        $invoice = $payment->invoice;
        $paymentNumber = $payment->payment_number;

        Payment::query()->whereKey($payment->id)->delete();

        $this->refreshInvoicePaidAmount($invoice);

        return redirect()->route('payments.index', $current_team->slug)
            ->with('message', "Payment {$paymentNumber} has been removed.");
    }

    private function paymentForTeam(Team $team, Payment $payment): void
    {
        if ($payment->team_id !== $team->id) {
            abort(403);
        }
    }

    private function refreshInvoicePaidAmount(Invoice $invoice): void
    {
        $paidAmount = (float) $invoice->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $totalAmount = (float) $invoice->total_amount;
        $status = $invoice->status;

        if ($paidAmount <= 0 && $status !== 'void') {
            $status = 'issued';
        }

        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            $status = 'paid';
        }

        $invoice->update([
            'paid_amount' => round($paidAmount, 2),
            'status' => $status,
        ]);
    }

    private function generatePaymentNumber(Team $team): string
    {
        $teamCode = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $team->slug), 0, 4));
        $teamCode = $teamCode !== '' ? $teamCode : 'TEAM';

        do {
            $candidate = sprintf(
                '%s-PAY-%s-%04d',
                $teamCode,
                Carbon::now()->format('ymd'),
                random_int(1, 9999),
            );
        } while ($team->payments()->where('payment_number', $candidate)->exists());

        return $candidate;
    }
}
