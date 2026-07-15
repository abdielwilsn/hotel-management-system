<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\SavePosCategoryRequest;
use App\Http\Requests\Pos\SavePosMenuItemRequest;
use App\Models\PosCategory;
use App\Models\PosMenuItem;
use App\Models\PosOutlet;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PosMenuController extends Controller
{
    /**
     * Admin menu editor for a single outlet: categories + items.
     */
    public function index(Request $request, Team $current_team, PosOutlet $pos_outlet): Response
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('create', [PosMenuItem::class, $current_team]);

        $categories = $pos_outlet->categories()->orderBy('name')->get(['id', 'name']);

        $items = $pos_outlet->menuItems()
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'pos_category_id', 'name', 'price', 'unit', 'track_stock', 'stock_quantity', 'is_active']);

        return Inertia::render('pos/Menu', [
            'outlet' => $pos_outlet->only('id', 'name', 'type'),
            'categories' => $categories,
            'items' => $items,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function storeCategory(SavePosCategoryRequest $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('create', [PosCategory::class, $current_team]);

        $category = PosCategory::query()->create($request->payload($current_team, $pos_outlet));

        return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Category {$category->name} has been created.");
    }

    public function updateCategory(SavePosCategoryRequest $request, Team $current_team, PosOutlet $pos_outlet, PosCategory $pos_category): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);
        $this->categoryForOutlet($pos_outlet, $pos_category);

        Gate::authorize('update', [$pos_category, $current_team]);

        $pos_category->update($request->payload($current_team, $pos_outlet));

        return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Category {$pos_category->name} has been updated.");
    }

    public function destroyCategory(Request $request, Team $current_team, PosOutlet $pos_outlet, PosCategory $pos_category): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);
        $this->categoryForOutlet($pos_outlet, $pos_category);

        Gate::authorize('delete', [$pos_category, $current_team]);

        if ($pos_category->menuItems()->exists()) {
            return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
                ->with('message', 'Category cannot be deleted while it has items.');
        }

        $name = $pos_category->name;
        $pos_category->delete();

        return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Category {$name} has been removed.");
    }

    public function storeItem(SavePosMenuItemRequest $request, Team $current_team, PosOutlet $pos_outlet): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);

        Gate::authorize('create', [PosMenuItem::class, $current_team]);

        $item = PosMenuItem::query()->create($request->payload($current_team, $pos_outlet));

        return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Item {$item->name} has been created.");
    }

    public function editItem(Request $request, Team $current_team, PosOutlet $pos_outlet, PosMenuItem $pos_menu_item): Response
    {
        $this->outletForTeam($current_team, $pos_outlet);
        $this->itemForOutlet($pos_outlet, $pos_menu_item);

        Gate::authorize('update', [$pos_menu_item, $current_team]);

        return Inertia::render('pos/MenuItemEdit', [
            'outlet' => $pos_outlet->only('id', 'name', 'type'),
            'item' => $pos_menu_item->only('id', 'pos_category_id', 'name', 'price', 'unit', 'track_stock', 'is_active'),
            'categories' => $pos_outlet->categories()->orderBy('name')->get(['id', 'name']),
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function updateItem(SavePosMenuItemRequest $request, Team $current_team, PosOutlet $pos_outlet, PosMenuItem $pos_menu_item): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);
        $this->itemForOutlet($pos_outlet, $pos_menu_item);

        Gate::authorize('update', [$pos_menu_item, $current_team]);

        $pos_menu_item->update($request->payload($current_team, $pos_outlet));

        return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Item {$pos_menu_item->name} has been updated.");
    }

    public function destroyItem(Request $request, Team $current_team, PosOutlet $pos_outlet, PosMenuItem $pos_menu_item): RedirectResponse
    {
        $this->outletForTeam($current_team, $pos_outlet);
        $this->itemForOutlet($pos_outlet, $pos_menu_item);

        Gate::authorize('delete', [$pos_menu_item, $current_team]);

        $name = $pos_menu_item->name;
        $pos_menu_item->delete();

        return redirect()->route('pos.menu', [$current_team->slug, $pos_outlet->id])
            ->with('message', "Item {$name} has been removed.");
    }

    private function outletForTeam(Team $team, PosOutlet $outlet): void
    {
        if ($outlet->team_id !== $team->id) {
            abort(403);
        }
    }

    private function categoryForOutlet(PosOutlet $outlet, PosCategory $category): void
    {
        if ($category->pos_outlet_id !== $outlet->id) {
            abort(403);
        }
    }

    private function itemForOutlet(PosOutlet $outlet, PosMenuItem $item): void
    {
        if ($item->pos_outlet_id !== $outlet->id) {
            abort(403);
        }
    }
}
