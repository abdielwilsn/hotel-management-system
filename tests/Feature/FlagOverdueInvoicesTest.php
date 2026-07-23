<?php

use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Invoices\InvoiceOverdue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('an unpaid invoice past its due date is flagged overdue and managers are notified', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $manager->teams()->attach($team, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'status' => 'partially_paid',
        'due_date' => now()->subDays(2)->toDateString(),
        'total_amount' => 500,
        'paid_amount' => 100,
    ]);

    $this->artisan('invoices:flag-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('overdue');
    Notification::assertSentTo(
        $manager,
        fn (InvoiceOverdue $notification) => $notification->invoice->is($invoice),
    );
});

test('an invoice not yet due is left alone', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'status' => 'issued',
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    $this->artisan('invoices:flag-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('issued');
    Notification::assertNothingSent();
});

test('a voided invoice past its due date is not flagged overdue', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'status' => 'void',
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $this->artisan('invoices:flag-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('void');
    Notification::assertNothingSent();
});
