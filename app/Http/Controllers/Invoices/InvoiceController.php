<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\SaveInvoiceRequest;
use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Invoice::class, $current_team]);

        $invoices = $current_team->invoices()
            ->with('booking:id,guest_name')
            ->orderByDesc('issue_date')
            ->get();

        $bookings = $current_team->bookings()
            ->orderByDesc('check_in_date')
            ->get(['id', 'guest_name', 'guest_email']);

        $statuses = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'void'];

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'bookings' => $bookings,
            'statuses' => $statuses,
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
     * @param array<string, mixed> $data
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
