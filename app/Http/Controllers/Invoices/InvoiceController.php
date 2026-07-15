<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\SaveInvoiceRequest;
use App\Models\Invoice;
use App\Models\Team;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Invoice::class, $current_team]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:draft,issued,partially_paid,paid,overdue,void'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,partial,paid'],
            'booking_id' => ['nullable', 'integer'],
            'issue_date_from' => ['nullable', 'date'],
            'issue_date_to' => ['nullable', 'date'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],
        ]);

        $invoices = $current_team->invoices()
            ->with('booking:id,guest_name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['booking_id'] ?? null, function (Builder $query, int $bookingId): void {
                $query->where('booking_id', $bookingId);
            })
            ->when($filters['issue_date_from'] ?? null, function (Builder $query, string $issueDateFrom): void {
                $query->whereDate('issue_date', '>=', $issueDateFrom);
            })
            ->when($filters['issue_date_to'] ?? null, function (Builder $query, string $issueDateTo): void {
                $query->whereDate('issue_date', '<=', $issueDateTo);
            })
            ->when($filters['due_date_from'] ?? null, function (Builder $query, string $dueDateFrom): void {
                $query->whereDate('due_date', '>=', $dueDateFrom);
            })
            ->when($filters['due_date_to'] ?? null, function (Builder $query, string $dueDateTo): void {
                $query->whereDate('due_date', '<=', $dueDateTo);
            })
            ->when($filters['payment_status'] ?? null, function (Builder $query, string $paymentStatus): void {
                if ($paymentStatus === 'unpaid') {
                    $query->where('paid_amount', '<=', 0);

                    return;
                }

                if ($paymentStatus === 'partial') {
                    $query
                        ->where('paid_amount', '>', 0)
                        ->whereColumn('paid_amount', '<', 'total_amount');

                    return;
                }

                $query
                    ->where('total_amount', '>', 0)
                    ->whereColumn('paid_amount', '>=', 'total_amount');
            })
            ->orderByDesc('issue_date')
            ->paginate(20)
            ->withQueryString();

        $bookings = $current_team->bookings()
            ->orderByDesc('check_in_date')
            ->get(['id', 'guest_name', 'guest_email']);

        $statuses = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'void'];

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices->items(),
            'pagination' => PaginationMeta::from($invoices),
            'bookings' => $bookings,
            'statuses' => $statuses,
            'paymentStatuses' => ['unpaid', 'partial', 'paid'],
            'filters' => Arr::only($filters, [
                'search',
                'status',
                'payment_status',
                'booking_id',
                'issue_date_from',
                'issue_date_to',
                'due_date_from',
                'due_date_to',
            ]),
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveInvoiceRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Invoice::class, $current_team]);

        $data = $request->validated();
        $data['invoice_number'] = $this->generateInvoiceNumber($current_team);
        $data['total_amount'] = $this->computeTotalAmount($data);

        $current_team->invoices()->create($data);

        return redirect()->route('invoices.index', $current_team->slug)
            ->with('message', "Invoice {$data['invoice_number']} has been created.");
    }

    public function edit(Request $request, Team $current_team, Invoice $invoice): Response
    {
        $this->invoiceForTeam($current_team, $invoice);

        Gate::authorize('update', [$invoice, $current_team]);

        $bookings = $current_team->bookings()
            ->orderByDesc('check_in_date')
            ->get(['id', 'guest_name', 'guest_email']);

        $statuses = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'void'];

        return Inertia::render('invoices/Edit', [
            'invoice' => $invoice->load('booking:id,guest_name'),
            'bookings' => $bookings,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveInvoiceRequest $request, Team $current_team, Invoice $invoice): RedirectResponse
    {
        $this->invoiceForTeam($current_team, $invoice);

        Gate::authorize('update', [$invoice, $current_team]);

        $data = $request->validated();
        $data['total_amount'] = $this->computeTotalAmount($data);

        $invoice->update($data);

        return redirect()->route('invoices.index', $current_team->slug)
            ->with('message', "Invoice {$invoice->invoice_number} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Invoice $invoice): RedirectResponse
    {
        $this->invoiceForTeam($current_team, $invoice);

        Gate::authorize('delete', [$invoice, $current_team]);

        $number = $invoice->invoice_number;
        Invoice::query()->whereKey($invoice->id)->delete();

        return redirect()->route('invoices.index', $current_team->slug)
            ->with('message', "Invoice {$number} has been removed.");
    }

    private function invoiceForTeam(Team $team, Invoice $invoice): void
    {
        if ($invoice->team_id !== $team->id) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function computeTotalAmount(array $data): float
    {
        return round(
            ((float) $data['subtotal'])
            + ((float) $data['tax_amount'])
            - ((float) $data['discount_amount']),
            2,
        );
    }

    private function generateInvoiceNumber(Team $team): string
    {
        $teamCode = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $team->slug), 0, 4));
        $teamCode = $teamCode !== '' ? $teamCode : 'TEAM';

        do {
            $candidate = sprintf(
                '%s-INV-%s-%04d',
                $teamCode,
                Carbon::now()->format('ymd'),
                random_int(1, 9999),
            );
        } while ($team->invoices()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
