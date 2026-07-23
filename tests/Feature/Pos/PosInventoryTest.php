<?php

use App\Models\PosMenuItem;
use App\Models\PosOutlet;
use App\Models\PosStockMovement;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Pos\MenuItemStockLow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * @return array{0: Team, 1: User, 2: PosOutlet}
 */
function posInventoryContext(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id, 'name' => 'Main Bar']);

    return [$team, $user, $outlet];
}

function trackedItem(Team $team, PosOutlet $outlet, int $stock = 0): PosMenuItem
{
    return PosMenuItem::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'price' => 1000,
        'track_stock' => true,
        'stock_quantity' => $stock,
    ]);
}

test('receiving stock increases on-hand and logs a movement with cost and supplier', function () {
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 10);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/stock/receive", [
            'pos_menu_item_id' => $item->id,
            'quantity' => 24,
            'unit_cost' => 650,
            'supplier' => 'ABC Drinks',
            'business_date' => now()->toDateString(),
        ])
        ->assertRedirect("/{$team->slug}/pos/{$outlet->id}/reports");

    expect($item->fresh()->stock_quantity)->toBe(34);

    $this->assertDatabaseHas('pos_stock_movements', [
        'pos_menu_item_id' => $item->id,
        'type' => 'received',
        'quantity' => 24,
        'balance_after' => 34,
        'supplier' => 'ABC Drinks',
    ]);
});

test('selling a tracked item decrements on-hand and logs a sold movement', function () {
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 10);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 3]],
    ])->assertRedirect();

    expect($item->fresh()->stock_quantity)->toBe(7);

    $this->assertDatabaseHas('pos_stock_movements', [
        'pos_menu_item_id' => $item->id,
        'type' => 'sold',
        'quantity' => -3,
        'balance_after' => 7,
    ]);
});

test('selling more than on-hand is blocked', function () {
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 2);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/orders", [
            'charge_type' => 'walk_in',
            'payment_mode' => 'cash',
            'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 5]],
        ])
        ->assertSessionHasErrors('items');

    // Nothing sold, stock untouched.
    expect($item->fresh()->stock_quantity)->toBe(2);
    expect(PosStockMovement::query()->where('pos_menu_item_id', $item->id)->count())->toBe(0);
});

test('an item with no stock cannot be sold', function () {
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 0);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/orders", [
            'charge_type' => 'walk_in',
            'payment_mode' => 'cash',
            'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items');
});

test('non-tracked items are never blocked by stock', function () {
    [$team, $user, $outlet] = posInventoryContext();
    $item = PosMenuItem::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'price' => 1000,
        'track_stock' => false,
        'stock_quantity' => 0,
    ]);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/orders", [
            'charge_type' => 'walk_in',
            'payment_mode' => 'cash',
            'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 99]],
        ])
        ->assertRedirect();
});

test('a sale that drops stock to the low threshold notifies managers once', function () {
    Notification::fake();
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 7);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 2]],
    ]);

    expect($item->fresh()->stock_quantity)->toBe(5);
    Notification::assertSentTo(
        $user,
        fn (MenuItemStockLow $notification) => $notification->level === 'low',
    );

    // Already at/below the threshold — selling more shouldn't re-fire "low".
    Notification::fake();
    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 1]],
    ]);

    Notification::assertNothingSent();
});

test('a sale that empties stock notifies managers that the item is out', function () {
    Notification::fake();
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 3);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 3]],
    ]);

    expect($item->fresh()->stock_quantity)->toBe(0);
    Notification::assertSentTo(
        $user,
        fn (MenuItemStockLow $notification) => $notification->level === 'out',
    );
});

test('non-tracked items never trigger a low-stock notification', function () {
    Notification::fake();
    [$team, $user, $outlet] = posInventoryContext();
    $item = PosMenuItem::factory()->create([
        'team_id' => $team->id,
        'pos_outlet_id' => $outlet->id,
        'price' => 1000,
        'track_stock' => false,
        'stock_quantity' => 0,
    ]);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 5]],
    ]);

    Notification::assertNothingSent();
});

test('receiving stock back above the threshold does not notify', function () {
    Notification::fake();
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 2);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/stock/receive", [
        'pos_menu_item_id' => $item->id,
        'quantity' => 24,
        'business_date' => now()->toDateString(),
    ]);

    Notification::assertNothingSent();
});

test('the end-of-day count corrects on-hand and logs an adjustment', function () {
    [$team, $user, $outlet] = posInventoryContext();
    $item = trackedItem($team, $outlet, 30);

    // Physically counted 25 (5 unaccounted for).
    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/stock", [
        'pos_menu_item_id' => $item->id,
        'business_date' => now()->toDateString(),
        'opening_stock' => 30,
        'new_stock' => 0,
        'closing_stock' => 25,
        'damaged' => 0,
        'is_closed' => true,
    ])->assertRedirect();

    expect($item->fresh()->stock_quantity)->toBe(25);

    $this->assertDatabaseHas('pos_stock_movements', [
        'pos_menu_item_id' => $item->id,
        'type' => 'adjustment',
        'quantity' => -5,
        'balance_after' => 25,
    ]);
});
