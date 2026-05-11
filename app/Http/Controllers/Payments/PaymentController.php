<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\SavePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Payment::class, $current_team]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:pending,completed,failed,refunded'],
            'method' => ['nullable', 'string', 'in:cash,card,bank_transfer,online,other'],
            'invoice_id' => ['nullable', 'integer'],
            'payment_date_from' => ['nullable', 'date'],
            'payment_date_to' => ['nullable', 'date'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payments = $current_team->payments()
            ->with('invoice:id,invoice_number,guest_name,total_amount,paid_amount')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search): void {
                            $invoiceQuery
                                ->where('invoice_number', 'like', "%{$search}%")
                                ->orWhere('guest_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['method'] ?? null, function (Builder $query, string $method): void {
                $query->where('method', $method);
            })
            ->when($filters['invoice_id'] ?? null, function (Builder $query, int $invoiceId): void {
                $query->where('invoice_id', $invoiceId);
            })
            ->when($filters['payment_date_from'] ?? null, function (Builder $query, string $paymentDateFrom): void {
                $query->whereDate('payment_date', '>=', $paymentDateFrom);
            })
            ->when($filters['payment_date_to'] ?? null, function (Builder $query, string $paymentDateTo): void {
                $query->whereDate('payment_date', '<=', $paymentDateTo);
            })
            ->when($filters['amount_min'] ?? null, function (Builder $query, string $amountMin): void {
                $query->where('amount', '>=', $amountMin);
            })
            ->when($filters['amount_max'] ?? null, function (Builder $query, string $amountMax): void {
                $query->where('amount', '<=', $amountMax);
            })
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
            'filters' => Arr::only($filters, [
                'search',
                'status',
                'method',
                'invoice_id',
                'payment_date_from',
                'payment_date_to',
                'amount_min',
                'amount_max',
            ]),
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
        } elseif ($paidAmount > 0 && $paidAmount < $totalAmount) {
            $status = 'partially_paid';
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
