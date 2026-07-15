<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\SaveExpenseRequest;
use App\Models\Expense;
use App\Models\Team;
use App\Support\PaginationMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Expense::class, $current_team]);

        $expenses = $current_team->expenses()
            ->orderByDesc('incurred_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses->items(),
            'pagination' => PaginationMeta::from($expenses),
            'categories' => ['utilities', 'maintenance', 'supplies', 'payroll', 'marketing', 'other'],
            'statuses' => ['pending', 'paid', 'cancelled'],
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveExpenseRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Expense::class, $current_team]);

        $expense = $current_team->expenses()->create($request->payload($current_team));

        return redirect()->route('expenses.index', $current_team->slug)
            ->with('message', "Expense {$expense->title} has been recorded.");
    }

    public function edit(Request $request, Team $current_team, Expense $expense): Response
    {
        $this->expenseForTeam($current_team, $expense);

        Gate::authorize('update', [$expense, $current_team]);

        return Inertia::render('expenses/Edit', [
            'expense' => $expense,
            'categories' => ['utilities', 'maintenance', 'supplies', 'payroll', 'marketing', 'other'],
            'statuses' => ['pending', 'paid', 'cancelled'],
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveExpenseRequest $request, Team $current_team, Expense $expense): RedirectResponse
    {
        $this->expenseForTeam($current_team, $expense);

        Gate::authorize('update', [$expense, $current_team]);

        $expense->update($request->payload($current_team));

        return redirect()->route('expenses.index', $current_team->slug)
            ->with('message', "Expense {$expense->title} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Expense $expense): RedirectResponse
    {
        $this->expenseForTeam($current_team, $expense);

        Gate::authorize('delete', [$expense, $current_team]);

        $title = $expense->title;
        $expense->delete();

        return redirect()->route('expenses.index', $current_team->slug)
            ->with('message', "Expense {$title} has been removed.");
    }

    private function expenseForTeam(Team $team, Expense $expense): void
    {
        if ($expense->team_id !== $team->id) {
            abort(403);
        }
    }
}
