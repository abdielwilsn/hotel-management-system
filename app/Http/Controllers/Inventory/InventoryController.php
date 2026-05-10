<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryCategoryRequest;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySale;
use App\Models\InventoryStockRecord;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [InventoryItem::class, $current_team]);

        $categories = InventoryCategory::query()
            ->forTeam($current_team)
            ->withCount('items')
            ->orderBy('name')
            ->get();

        $items = InventoryItem::query()
            ->forTeam($current_team)
            ->with('category:id,name')
            ->orderBy('name')
            ->limit(250)
            ->get();

        $itemCount = InventoryItem::query()->forTeam($current_team)->count();

        $recentSales = InventorySale::query()
            ->forTeam($current_team)
            ->with('item:id,name')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $recentStock = InventoryStockRecord::query()
            ->forTeam($current_team)
            ->with('item:id,name,unit')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return Inertia::render('inventory/Index', [
            'summary' => [
                'categories' => $categories->count(),
                'items' => $itemCount,
                'sales_records' => InventorySale::query()->forTeam($current_team)->count(),
                'stock_records' => InventoryStockRecord::query()->forTeam($current_team)->count(),
                'sales_value' => round((float) InventorySale::query()->forTeam($current_team)->sum('total_amount'), 2),
            ],
            'categories' => $categories,
            'items' => $items,
            'recentSales' => $recentSales,
            'recentStock' => $recentStock,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveInventoryItemRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [InventoryItem::class, $current_team]);

        $item = $current_team->inventoryCategories()
            ->findOrFail((int) $request->validated('inventory_category_id'))
            ->items()
            ->create($request->payload($current_team));

        return redirect()->route('inventory.index', $current_team->slug)
            ->with('message', "Inventory item {$item->name} has been created.");
    }

    public function storeCategory(SaveInventoryCategoryRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [InventoryCategory::class, $current_team]);

        $category = InventoryCategory::query()->create($request->payload($current_team));

        return redirect()->route('inventory.index', $current_team->slug)
            ->with('message', "Category {$category->name} has been created.");
    }

    public function edit(Request $request, Team $current_team, InventoryItem $inventory_item): Response
    {
        $this->itemForTeam($current_team, $inventory_item);

        Gate::authorize('update', [$inventory_item, $current_team]);

        $categories = InventoryCategory::query()
            ->forTeam($current_team)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('inventory/Edit', [
            'item' => $inventory_item->load('category:id,name,type'),
            'categories' => $categories,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveInventoryItemRequest $request, Team $current_team, InventoryItem $inventory_item): RedirectResponse
    {
        $this->itemForTeam($current_team, $inventory_item);

        Gate::authorize('update', [$inventory_item, $current_team]);

        $inventory_item->update($request->payload($current_team));

        return redirect()->route('inventory.index', $current_team->slug)
            ->with('message', "Inventory item {$inventory_item->name} has been updated.");
    }

    public function editCategory(Request $request, Team $current_team, InventoryCategory $inventory_category): Response
    {
        $this->categoryForTeam($current_team, $inventory_category);

        Gate::authorize('update', [$inventory_category, $current_team]);

        return Inertia::render('inventory/EditCategory', [
            'category' => $inventory_category,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function updateCategory(
        SaveInventoryCategoryRequest $request,
        Team $current_team,
        InventoryCategory $inventory_category,
    ): RedirectResponse {
        $this->categoryForTeam($current_team, $inventory_category);

        Gate::authorize('update', [$inventory_category, $current_team]);

        $inventory_category->update($request->payload($current_team));

        return redirect()->route('inventory.index', $current_team->slug)
            ->with('message', "Category {$inventory_category->name} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, InventoryItem $inventory_item): RedirectResponse
    {
        $this->itemForTeam($current_team, $inventory_item);

        Gate::authorize('delete', [$inventory_item, $current_team]);

        $name = $inventory_item->name;
        $inventory_item->delete();

        return redirect()->route('inventory.index', $current_team->slug)
            ->with('message', "Inventory item {$name} has been removed.");
    }

    public function destroyCategory(Request $request, Team $current_team, InventoryCategory $inventory_category): RedirectResponse
    {
        $this->categoryForTeam($current_team, $inventory_category);

        Gate::authorize('delete', [$inventory_category, $current_team]);

        if ($inventory_category->items()->exists()) {
            return redirect()->route('inventory.index', $current_team->slug)
                ->with('message', 'Category cannot be deleted while it has items.');
        }

        $name = $inventory_category->name;
        $inventory_category->delete();

        return redirect()->route('inventory.index', $current_team->slug)
            ->with('message', "Category {$name} has been removed.");
    }

    private function itemForTeam(Team $team, InventoryItem $item): void
    {
        if ($item->team_id !== $team->id) {
            abort(403);
        }
    }

    private function categoryForTeam(Team $team, InventoryCategory $category): void
    {
        if ($category->team_id !== $team->id) {
            abort(403);
        }
    }
}
