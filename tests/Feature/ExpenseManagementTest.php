<?php

use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team members can view expenses index page', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->get("/{$team->slug}/expenses")
        ->assertOk();
});

test('admins can create expenses', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->post("/{$team->slug}/expenses", [
            'title' => 'Generator service',
            'category' => 'maintenance',
            'amount' => 750.45,
            'incurred_date' => '2026-05-01',
            'vendor' => 'PowerFix Ltd.',
            'status' => 'paid',
            'description' => 'Scheduled preventive maintenance',
        ])
        ->assertRedirect("/{$team->slug}/expenses");

    $this->assertDatabaseHas('expenses', [
        'team_id' => $team->id,
        'title' => 'Generator service',
        'category' => 'maintenance',
        'status' => 'paid',
    ]);
});

test('admins can update expenses', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $expense = Expense::factory()->create([
        'team_id' => $team->id,
        'title' => 'Laundry supplies',
        'category' => 'supplies',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/expenses/{$expense->id}", [
            'title' => 'Laundry supplies restock',
            'category' => 'supplies',
            'amount' => 330,
            'incurred_date' => '2026-05-02',
            'vendor' => 'CleanCo',
            'status' => 'paid',
            'description' => 'Monthly refresh',
        ])
        ->assertRedirect("/{$team->slug}/expenses");

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'title' => 'Laundry supplies restock',
        'status' => 'paid',
    ]);
});

test('admins can delete expenses', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $expense = Expense::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete("/{$team->slug}/expenses/{$expense->id}")
        ->assertRedirect("/{$team->slug}/expenses");

    $this->assertModelMissing($expense);
});

test('members cannot create expenses', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);

    $this->actingAs($user)
        ->post("/{$team->slug}/expenses", [
            'title' => 'Invalid attempt',
            'category' => 'other',
            'amount' => 99,
            'incurred_date' => '2026-05-01',
            'status' => 'paid',
        ])
        ->assertForbidden();
});

test('expense amount must be greater than zero', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $this->actingAs($user)
        ->post("/{$team->slug}/expenses", [
            'title' => 'Water bill',
            'category' => 'utilities',
            'amount' => 0,
            'incurred_date' => '2026-05-01',
            'status' => 'paid',
        ])
        ->assertSessionHasErrors('amount');
});

test('users cannot update expenses from another team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create();
    $user->teams()->attach($team, ['role' => 'admin']);

    $expense = Expense::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->patch("/{$team->slug}/expenses/{$expense->id}", [
            'title' => 'Attempted update',
            'category' => 'other',
            'amount' => 120,
            'incurred_date' => '2026-05-01',
            'status' => 'paid',
        ])
        ->assertForbidden();
});
