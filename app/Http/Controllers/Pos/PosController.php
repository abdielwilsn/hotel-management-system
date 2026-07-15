<?php

namespace App\Http\Controllers\Pos;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ReceivePosStockRequest;
use App\Http\Requests\Pos\SavePosStockRequest;
use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\PosStockMovement;
use App\Models\PosStockRecord;
use App\Models\Team;
use App\Support\PosInventoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(private PosInventoryService $inventory) {}

    /**
     * Outlet picker + today's takings for each outlet the user can operate.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [PosOutlet::class, $current_team]);

        $outlets = $this->accessibleOutlets($request, $current_team);
        $today = now()->toDateString();

        $cards = $outlets->map(fn (PosOutlet $outlet) => [
            'id' => $outlet->id,
            'name' => $outlet->name,
            'type' => $outlet->type,
            'status' => $outlet->status,
            'today_orders' => PosOrder::query()->where('pos_outlet_id', $outlet->id)->whereDate('business_date', $today)->count(),
            'today_sales' => round((float) PosOrder::query()->where('pos_outlet_id', $outlet->id)->whereDate('business_date', $today)->sum('total'), 2),
        ])->values();

        return Inertia::render('pos/Index', [
            'outlets' => $cards,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    /**
     * The selling screen for a single outlet.
     */
    public function terminal(Request $request, Team $current_team, PosOutlet $pos_outlet): Response
    {
        Gate::authorize('operate', [$pos_outlet, $current_team]);

        $categories = $pos_outlet->categories()->orderBy('name')->get(['id', 'name']);

        $items = $pos_outlet->menuItems()
            ->active()
            ->orderBy('name')
            ->get(['id', 'pos_category_id', 'name', 'price', 'unit', 'track_stock', 'stock_quantity']);

        $activeBookings = $current_team->bookings()
            ->active()
            ->with('room:id,room_number')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($booking) => [
                'id' => $booking->id,
                'guest_name' => $booking->guest_name,
                'room_number' => $booking->room?->room_number,
            ]);

        return Inertia::render('pos/Terminal', [
            'outlet' => $pos_outlet->only('id', 'name', 'type'),
            'categories' => $categories,
            'items' => $items,
            'activeBookings' => $activeBookings,
            'defaultServer' => $request->user()->name,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    /**
     * Sales + daily stock reconciliation for a single outlet.
     */
    public function reports(Request $request, Team $current_team, PosOutlet $pos_outlet): Response
    {
        Gate::authorize('operate', [$pos_outlet, $current_team]);

        $recentOrders = PosOrder::query()
            ->where('pos_outlet_id', $pos_outlet->id)
            ->withCount('items')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $stockRecords = PosStockRecord::query()
            ->where('pos_outlet_id', $pos_outlet->id)
            ->with('menuItem:id,name,unit')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $trackedItems = $pos_outlet->menuItems()
            ->where('track_stock', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'stock_quantity']);

        $recentMovements = PosStockMovement::query()
            ->where('pos_outlet_id', $pos_outlet->id)
            ->with('menuItem:id,name,unit')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Inertia::render('pos/Reports', [
            'outlet' => $pos_outlet->only('id', 'name', 'type'),
            'summary' => [
                'orders' => PosOrder::query()->where('pos_outlet_id', $pos_outlet->id)->count(),
                'sales_value' => round((float) PosOrder::query()->where('pos_outlet_id', $pos_outlet->id)->sum('total'), 2),
                'items' => $pos_outlet->menuItems()->count(),
            ],
            'recentOrders' => $recentOrders,
            'stockRecords' => $stockRecords,
            'trackedItems' => $trackedItems,
            'recentMovements' => $recentMovements,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    /**
     * Log a delivery of new stock, which increments the item's running on-hand.
     */
    public function receiveStock(ReceivePosStockRequest $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        Gate::authorize('operate', [$pos_outlet, $current_team]);

        $validated = $request->validated();

        /** @var PosMenuItem $item */
        $item = $pos_outlet->menuItems()->findOrFail((int) $validated['pos_menu_item_id']);

        $this->inventory->receive($item, (int) $validated['quantity'], [
            'business_date' => $validated['business_date'],
            'unit_cost' => $validated['unit_cost'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'recorded_by' => $request->user()->name,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('pos.reports', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Received {$validated['quantity']} × {$item->name} into stock.");
    }

    /**
     * Save (upsert) a daily stock reconciliation record for a tracked item.
     */
    public function storeStock(SavePosStockRequest $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        Gate::authorize('operate', [$pos_outlet, $current_team]);

        $validated = $request->validated();

        /** @var PosMenuItem $item */
        $item = $pos_outlet->menuItems()->findOrFail((int) $validated['pos_menu_item_id']);

        $totalStock = (int) $validated['opening_stock'] + (int) $validated['new_stock'];

        // whereDate matches regardless of the stored time component on business_date.
        $record = PosStockRecord::query()
            ->where('pos_menu_item_id', $item->id)
            ->whereDate('business_date', $validated['business_date'])
            ->first()
            ?? new PosStockRecord([
                'pos_menu_item_id' => $item->id,
                'business_date' => $validated['business_date'],
            ]);

        $salesQty = (int) $record->sales_qty;
        $expectedClosing = $totalStock - $salesQty - (int) $validated['damaged'];
        $countedClosing = (int) $validated['closing_stock'];
        $shortage = max($expectedClosing - $countedClosing, 0);
        $excess = max($countedClosing - $expectedClosing, 0);

        $record->fill([
            'team_id' => $current_team->id,
            'pos_outlet_id' => $pos_outlet->id,
            'opening_stock' => (int) $validated['opening_stock'],
            'new_stock' => (int) $validated['new_stock'],
            'total_stock' => $totalStock,
            'closing_stock' => $countedClosing,
            'damaged' => (int) $validated['damaged'],
            'shortage' => $shortage,
            'excess' => $excess,
            'closing_value' => round($countedClosing * (float) $item->price, 2),
            'recorded_by' => $validated['recorded_by'] ?? $request->user()->name,
            'notes' => $validated['notes'] ?? null,
            'is_closed' => (bool) $validated['is_closed'],
        ])->save();

        // The physical count is the source of truth — correct the running on-hand
        // to match it, logging the variance as an adjustment movement.
        $this->inventory->adjustToCount($item, $countedClosing, [
            'business_date' => $validated['business_date'],
            'recorded_by' => $validated['recorded_by'] ?? $request->user()->name,
            'notes' => 'Stock-take correction',
        ]);

        return redirect()->route('pos.reports', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Stock record for {$item->name} has been saved.");
    }

    /**
     * The outlets the current user is allowed to operate.
     *
     * @return Collection<int, PosOutlet>
     */
    private function accessibleOutlets(Request $request, Team $team): Collection
    {
        $query = PosOutlet::query()->forTeam($team)->active()->orderBy('name');

        $role = $request->user()->teamRole($team);

        if ($role === null || ! $role->isAtLeast(TeamRole::Member)) {
            $query->whereIn('id', $request->user()->posOutlets()->select('pos_outlets.id'));
        }

        return $query->get();
    }
}
