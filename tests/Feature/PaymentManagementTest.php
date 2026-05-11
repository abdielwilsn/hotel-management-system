<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('team members can view the payments index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $response = $this->actingAs($user)->get("/{$team->slug}/payments");

    $response->assertStatus(200);
});

test('admins can create completed payments and invoice paid amount is updated', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 500,
        'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/payments", [
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-2026-1001',
        'payment_date' => '2026-07-01',
        'amount' => 500,
        'method' => 'card',
        'status' => 'completed',
        'reference' => 'REF-1001',
        'notes' => 'Paid at front desk',
    ]);

    $response->assertRedirect("/{$team->slug}/payments");

    $this->assertDatabaseHas('payments', [
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'status' => 'completed',
    ]);

    $createdPayment = Payment::query()
        ->where('team_id', $team->id)
        ->where('invoice_id', $invoice->id)
        ->where('amount', 500)
        ->first();

    expect($createdPayment)->not->toBeNull();
    expect($createdPayment?->payment_number)->not->toBeEmpty();

    expect((float) $invoice->fresh()->paid_amount)->toBe(500.0);
});

test('admins can update payments and invoice paid amount is recalculated', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 500,
        'paid_amount' => 150,
    ]);

    $payment = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'status' => 'completed',
        'payment_number' => 'PAY-2026-1002',
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 50,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->patch("/{$team->slug}/payments/{$payment->id}", [
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-2026-1002',
        'payment_date' => '2026-07-02',
        'amount' => 450,
        'method' => 'bank_transfer',
        'status' => 'completed',
        'reference' => 'REF-2002',
        'notes' => 'Updated transfer amount',
    ]);

    $response->assertRedirect("/{$team->slug}/payments");

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'amount' => 450,
        'method' => 'bank_transfer',
    ]);

    expect((float) $invoice->fresh()->paid_amount)->toBe(500.0);
});

test('admins can delete payments and invoice paid amount is recalculated', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 500,
        'paid_amount' => 170,
    ]);

    $paymentToDelete = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'status' => 'completed',
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 70,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/payments/{$paymentToDelete->id}");

    $response->assertRedirect("/{$team->slug}/payments");
    $this->assertModelMissing($paymentToDelete);

    expect((float) $invoice->fresh()->paid_amount)->toBe(70.0);
});

test('members can create completed payments', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 100,
        'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/payments", [
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-2026-1100',
        'payment_date' => '2026-07-03',
        'amount' => 100.00,
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $response->assertRedirect("/{$team->slug}/payments");
    $this->assertDatabaseHas('payments', [
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'status' => 'completed',
    ]);
});

test('members cannot delete payments', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $invoice = Invoice::factory()->create(['team_id' => $team->id]);
    $payment = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
    ]);

    $response = $this->actingAs($user)->delete("/{$team->slug}/payments/{$payment->id}");

    $response->assertStatus(403);
    $this->assertModelExists($payment);
});

test('invoice must belong to the same team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $otherInvoice = Invoice::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->post("/{$team->slug}/payments", [
        'invoice_id' => $otherInvoice->id,
        'payment_number' => 'PAY-2026-1200',
        'payment_date' => '2026-07-03',
        'amount' => 100,
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $response->assertSessionHasErrors('invoice_id');
});

test('completed payments can be recorded as partial payments up to the outstanding balance', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 200,
        'paid_amount' => 50,
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 50,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/payments", [
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-2026-1300',
        'payment_date' => '2026-07-03',
        'amount' => 100,
        'method' => 'online',
        'status' => 'completed',
    ]);

    $response->assertRedirect("/{$team->slug}/payments");

    expect((float) $invoice->fresh()->paid_amount)->toBe(150.0);
    expect($invoice->fresh()->status)->toBe('partially_paid');
});

test('completed payments cannot exceed the outstanding invoice balance', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'total_amount' => 200,
        'paid_amount' => 50,
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'amount' => 50,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->post("/{$team->slug}/payments", [
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-2026-1301',
        'payment_date' => '2026-07-03',
        'amount' => 170,
        'method' => 'online',
        'status' => 'completed',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('payments index can be filtered by method, status, and date', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $invoice = Invoice::factory()->create(['team_id' => $team->id]);

    $targetPayment = Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'method' => 'card',
        'status' => 'completed',
        'payment_date' => '2026-10-12',
    ]);

    Payment::factory()->create([
        'team_id' => $team->id,
        'invoice_id' => $invoice->id,
        'method' => 'cash',
        'status' => 'pending',
        'payment_date' => '2026-10-01',
    ]);

    $this->actingAs($user)
        ->get("/{$team->slug}/payments?method=card&status=completed&payment_date_from=2026-10-10")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.0.id', $targetPayment->id)
            ->where('payments', fn ($payments) => count($payments) === 1));
});
