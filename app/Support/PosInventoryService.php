<?php

namespace App\Support;

use App\Enums\Ability;
use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosStockMovement;
use App\Notifications\Pos\MenuItemStockLow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PosInventoryService
{
    /**
     * Below this, front desk/the outlet should reorder; at or below zero, the
     * item can't be sold at all. One constant for every item for now — simple
     * and good enough until a specific item needs its own bar.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * Apply a stock movement: adjust the item's running on-hand quantity and
     * append a ledger row recording the resulting balance. This is the single
     * choke-point through which stock levels ever change.
     *
     * @param  array{business_date?: string, unit_cost?: float|null, supplier?: string|null, reference?: string|null, recorded_by?: string|null, notes?: string|null, pos_order_id?: int|null}  $meta
     */
    public function record(PosMenuItem $item, string $type, int $delta, array $meta = []): PosStockMovement
    {
        return DB::transaction(function () use ($item, $type, $delta, $meta): PosStockMovement {
            // Lock the row so concurrent sales can't race the balance.
            $current = (int) PosMenuItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->value('stock_quantity');

            $balanceAfter = $current + $delta;

            $item->update(['stock_quantity' => $balanceAfter]);

            $this->notifyIfCrossedLowStock($item, $current, $balanceAfter);

            return PosStockMovement::query()->create([
                'team_id' => $item->team_id,
                'pos_outlet_id' => $item->pos_outlet_id,
                'pos_menu_item_id' => $item->id,
                'pos_order_id' => $meta['pos_order_id'] ?? null,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $balanceAfter,
                'unit_cost' => $meta['unit_cost'] ?? null,
                'supplier' => $meta['supplier'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'recorded_by' => $meta['recorded_by'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'business_date' => $meta['business_date'] ?? now()->toDateString(),
            ]);
        });
    }

    /**
     * Alert the outlet/its managers only when a movement crosses into "low" or
     * "out" territory — not on every sale while it's already below the mark,
     * or receiving stock would spam the same alert on every delivery too.
     */
    private function notifyIfCrossedLowStock(PosMenuItem $item, int $before, int $after): void
    {
        if (! $item->track_stock) {
            return;
        }

        $level = match (true) {
            $after <= 0 && $before > 0 => 'out',
            $after <= self::LOW_STOCK_THRESHOLD && $before > self::LOW_STOCK_THRESHOLD => 'low',
            default => null,
        };

        if ($level === null) {
            return;
        }

        $team = $item->team;

        if ($team === null) {
            return;
        }

        Notification::send(
            $team->membersWithAbility(Ability::ManagePos),
            new MenuItemStockLow($item, $level),
        );
    }

    /**
     * Log a delivery of new stock (increments on-hand).
     *
     * @param  array{business_date?: string, unit_cost?: float|null, supplier?: string|null, recorded_by?: string|null, notes?: string|null}  $meta
     */
    public function receive(PosMenuItem $item, int $quantity, array $meta = []): PosStockMovement
    {
        return $this->record($item, 'received', abs($quantity), $meta);
    }

    /**
     * Decrement on-hand for a sale, linked to the order.
     */
    public function sell(PosMenuItem $item, int $quantity, PosOrder $order): PosStockMovement
    {
        return $this->record($item, 'sold', -abs($quantity), [
            'pos_order_id' => $order->id,
            'business_date' => $order->business_date instanceof \DateTimeInterface
                ? $order->business_date->format('Y-m-d')
                : (string) $order->business_date,
            'reference' => $order->order_number,
            'recorded_by' => $order->served_by,
        ]);
    }

    /**
     * Record damaged/spoiled units (decrements on-hand).
     *
     * @param  array{business_date?: string, recorded_by?: string|null, notes?: string|null}  $meta
     */
    public function damage(PosMenuItem $item, int $quantity, array $meta = []): PosStockMovement
    {
        return $this->record($item, 'damaged', -abs($quantity), $meta);
    }

    /**
     * Correct on-hand to a physically counted total, logging the variance as an
     * adjustment movement. Returns null when the count already matches.
     *
     * @param  array{business_date?: string, recorded_by?: string|null, notes?: string|null}  $meta
     */
    public function adjustToCount(PosMenuItem $item, int $countedQuantity, array $meta = []): ?PosStockMovement
    {
        $delta = $countedQuantity - (int) $item->stock_quantity;

        if ($delta === 0) {
            return null;
        }

        return $this->record($item, 'adjustment', $delta, $meta);
    }
}
