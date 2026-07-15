<?php

use App\Models\Booking;
use App\Models\PosMenuItem;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\PosStockRecord;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function posSellingContext(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);
    $outlet = PosOutlet::factory()->bar()->create(['team_id' => $team->id, 'name' => 'Main Bar']);

    return [$team, $user, $outlet];
}

test('a walk-in order is recorded with line items and correct totals', function () {
    [$team, $user, $outlet] = posSellingContext();

    $beer = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 1000, 'track_stock' => false]);
    $wine = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 2500, 'track_stock' => false]);

    $response = $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'served_by' => 'Joe',
        'items' => [
            ['pos_menu_item_id' => $beer->id, 'quantity' => 2],
            ['pos_menu_item_id' => $wine->id, 'quantity' => 1],
        ],
    ]);

    $order = PosOrder::query()->first();

    expect($order)->not->toBeNull();
    expect((float) $order->total)->toBe(4500.0);
    expect($order->status)->toBe('paid');
    expect($order->payment_mode)->toBe('cash');
    expect($order->items()->count())->toBe(2);

    $response->assertRedirect("/{$team->slug}/pos/orders/{$order->id}/receipt");
});

test('selling a stock-tracked item increases its daily sales quantity', function () {
    [$team, $user, $outlet] = posSellingContext();

    $item = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 800, 'track_stock' => true]);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [
            ['pos_menu_item_id' => $item->id, 'quantity' => 3],
        ],
    ]);

    $this->assertDatabaseHas('pos_stock_records', [
        'pos_menu_item_id' => $item->id,
        'sales_qty' => 3,
        'sales_value' => 2400,
    ]);
});

test('selling the same tracked item twice in a day accumulates into one stock record', function () {
    [$team, $user, $outlet] = posSellingContext();

    $item = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 1500, 'track_stock' => true]);

    $payload = [
        'charge_type' => 'walk_in',
        'payment_mode' => 'cash',
        'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 1]],
    ];

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", $payload)->assertRedirect();
    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", $payload)->assertRedirect();

    // A single record for the day, with the two sales summed — no unique-constraint crash.
    expect(PosStockRecord::query()->where('pos_menu_item_id', $item->id)->count())->toBe(1);
    $this->assertDatabaseHas('pos_stock_records', [
        'pos_menu_item_id' => $item->id,
        'sales_qty' => 2,
        'sales_value' => 3000,
    ]);
});

test('an order charged to a room is posted onto the booking invoice', function () {
    [$team, $user, $outlet] = posSellingContext();

    $room = Room::factory()->create(['team_id' => $team->id, 'room_number' => 204]);
    $booking = Booking::factory()->create([
        'team_id' => $team->id,
        'room_id' => $room->id,
        'guest_name' => 'Ada Lovelace',
        'status' => 'checked_in',
    ]);

    $item = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 1500, 'track_stock' => false]);

    $this->actingAs($user)->post("/{$team->slug}/pos/{$outlet->id}/orders", [
        'charge_type' => 'room',
        'payment_mode' => 'room',
        'booking_id' => $booking->id,
        'items' => [
            ['pos_menu_item_id' => $item->id, 'quantity' => 2],
        ],
    ]);

    $order = PosOrder::query()->first();
    expect($order->charge_type)->toBe('room');
    expect($order->booking_id)->toBe($booking->id);
    expect($order->room_number)->toBe(204);

    $this->assertDatabaseHas('invoices', [
        'booking_id' => $booking->id,
        'total_amount' => 3000,
    ]);
});

test('charging to a room requires a booking', function () {
    [$team, $user, $outlet] = posSellingContext();
    $item = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id, 'price' => 500]);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/orders", [
            'charge_type' => 'room',
            'payment_mode' => 'room',
            'items' => [['pos_menu_item_id' => $item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('booking_id');
});

test('an order cannot include items from another outlet', function () {
    [$team, $user, $outlet] = posSellingContext();
    $otherOutlet = PosOutlet::factory()->create(['team_id' => $team->id]);
    $foreignItem = PosMenuItem::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $otherOutlet->id]);

    $this->actingAs($user)
        ->post("/{$team->slug}/pos/{$outlet->id}/orders", [
            'charge_type' => 'walk_in',
            'payment_mode' => 'cash',
            'items' => [['pos_menu_item_id' => $foreignItem->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items.0.pos_menu_item_id');
});

test('the receipt page renders for a completed order', function () {
    [$team, $user, $outlet] = posSellingContext();
    $order = PosOrder::factory()->create(['team_id' => $team->id, 'pos_outlet_id' => $outlet->id]);

    $this->actingAs($user)
        ->get("/{$team->slug}/pos/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('pos/Receipt'));
});
