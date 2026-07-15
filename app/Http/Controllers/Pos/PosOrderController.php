<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\PosStockRecord;
use App\Models\Team;
use App\Support\PosInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosOrderController extends Controller
{
    public function __construct(private PosInventoryService $inventory) {}

    /**
     * Ring up a multi-item order, take payment (or charge it to a room), and record stock.
     */
    public function store(StorePosOrderRequest $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        Gate::authorize('operate', [$pos_outlet, $current_team]);

        $validated = $request->validated();
        $businessDate = now()->toDateString();

        // Snapshot each line from the current menu so later price/name edits don't mutate history.
        $menuItems = PosMenuItem::query()
            ->where('pos_outlet_id', $pos_outlet->id)
            ->whereIn('id', collect($validated['items'])->pluck('pos_menu_item_id'))
            ->get()
            ->keyBy('id');

        $this->assertStockAvailable($validated['items'], $menuItems);

        $booking = null;
        if ($validated['charge_type'] === 'room') {
            /** @var Booking $booking */
            $booking = $current_team->bookings()->with('room:id,room_number')->findOrFail((int) $validated['booking_id']);
        }

        $order = DB::transaction(function () use ($request, $current_team, $pos_outlet, $validated, $businessDate, $menuItems, $booking): PosOrder {
            $subtotal = 0.0;
            $lines = [];

            foreach ($validated['items'] as $line) {
                $item = $menuItems[$line['pos_menu_item_id']];
                $quantity = (int) $line['quantity'];
                $lineTotal = round((float) $item->price * $quantity, 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $order = PosOrder::query()->create([
                'team_id' => $current_team->id,
                'pos_outlet_id' => $pos_outlet->id,
                'order_number' => $this->nextOrderNumber($pos_outlet, $businessDate),
                'status' => 'paid',
                'charge_type' => $validated['charge_type'],
                'booking_id' => $booking?->id,
                'room_number' => $booking?->room?->room_number,
                'guest_name' => $booking?->guest_name,
                'payment_mode' => $validated['payment_mode'],
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'served_by' => $validated['served_by'] ?? $request->user()->name,
                'business_date' => $businessDate,
                'opened_at' => now(),
                'paid_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var PosMenuItem $item */
                $item = $line['item'];

                $order->items()->create([
                    'pos_menu_item_id' => $item->id,
                    'name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                if ($item->track_stock) {
                    $this->inventory->sell($item, $line['quantity'], $order);
                    $this->recordStockSale($current_team, $pos_outlet, $item, $businessDate, $line['quantity'], $line['line_total']);
                }
            }

            if ($booking !== null) {
                $this->postToBookingFolio($current_team, $booking, $order);
            }

            return $order;
        });

        return redirect()->route('pos.orders.receipt', [$current_team->slug, $order->id])
            ->with('message', "Order {$order->order_number} completed.");
    }

    /**
     * Thermal (58/80mm) receipt for a completed order.
     */
    public function receipt(Request $request, Team $current_team, PosOrder $pos_order): Response
    {
        abort_unless($pos_order->team_id === $current_team->id, 403);

        $outlet = $pos_order->outlet;
        Gate::authorize('operate', [$outlet, $current_team]);

        $pos_order->load('items:id,pos_order_id,name,unit_price,quantity,line_total');

        return Inertia::render('pos/Receipt', [
            'order' => $pos_order,
            'outlet' => $outlet->only('id', 'name', 'type'),
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    /**
     * Block the sale when a stock-tracked item doesn't have enough on hand.
     *
     * @param  array<int, array{pos_menu_item_id: int, quantity: int}>  $items
     * @param  Collection<int, PosMenuItem>  $menuItems
     */
    private function assertStockAvailable(array $items, $menuItems): void
    {
        $requested = [];

        foreach ($items as $line) {
            $id = (int) $line['pos_menu_item_id'];
            $requested[$id] = ($requested[$id] ?? 0) + (int) $line['quantity'];
        }

        foreach ($requested as $id => $quantity) {
            $item = $menuItems[$id] ?? null;

            if ($item && $item->track_stock && $quantity > (int) $item->stock_quantity) {
                throw ValidationException::withMessages([
                    'items' => "Not enough stock for {$item->name}. Only {$item->stock_quantity} left in stock.",
                ]);
            }
        }
    }

    /**
     * Increment (or open) the item's daily stock record with this sale.
     */
    private function recordStockSale(Team $team, PosOutlet $outlet, PosMenuItem $item, string $businessDate, int $quantity, float $lineTotal): void
    {
        // Use whereDate so the lookup matches regardless of the stored time
        // component (the `date` cast persists business_date as "Y-m-d 00:00:00").
        $record = PosStockRecord::query()
            ->where('pos_menu_item_id', $item->id)
            ->whereDate('business_date', $businessDate)
            ->first()
            ?? new PosStockRecord([
                'pos_menu_item_id' => $item->id,
                'business_date' => $businessDate,
                'team_id' => $team->id,
                'pos_outlet_id' => $outlet->id,
            ]);

        $record->sales_qty = (int) $record->sales_qty + $quantity;
        $record->sales_value = round((float) $record->sales_value + $lineTotal, 2);
        $record->closing_stock = max((int) $record->total_stock - (int) $record->sales_qty - (int) $record->damaged, 0);
        $record->save();
    }

    /**
     * Post the order total onto the guest's folio (their booking invoice) at checkout.
     */
    private function postToBookingFolio(Team $team, Booking $booking, PosOrder $order): void
    {
        /** @var Invoice $invoice */
        $invoice = $booking->invoice()->first() ?? Invoice::query()->create([
            'team_id' => $team->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-'.strtoupper(bin2hex(random_bytes(3))),
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'status' => 'issued',
        ]);

        $invoice->subtotal = round((float) $invoice->subtotal + (float) $order->total, 2);
        $invoice->total_amount = round((float) $invoice->total_amount + (float) $order->total, 2);
        $invoice->save();
    }

    /**
     * Generate a per-outlet daily sequence like BAR-20260715-0007.
     */
    private function nextOrderNumber(PosOutlet $outlet, string $businessDate): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $outlet->name) ?: 'POS', 0, 3));
        $datePart = str_replace('-', '', $businessDate);

        $countToday = PosOrder::query()
            ->where('pos_outlet_id', $outlet->id)
            ->whereDate('business_date', $businessDate)
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $datePart, $countToday + 1);
    }
}
