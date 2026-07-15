<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\PosCategory;
use App\Models\PosMenuItem;
use App\Models\PosOutlet;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use App\Support\PosInventoryService;
use Illuminate\Database\Seeder;

class PosSeeder extends Seeder
{
    /**
     * Seed demo Bar & Restaurant outlets, menus, and a POS-staff user.
     */
    public function run(): void
    {
        $team = Team::query()->firstOrCreate(
            ['slug' => 'anns-haven'],
            ['name' => "Ann's Haven", 'is_personal' => false],
        );

        $bar = $this->outlet($team, 'Main Bar', 'bar');
        $restaurant = $this->outlet($team, 'Restaurant', 'restaurant');

        $this->menu($bar, [
            'Beers' => [['Local Lager', 1000, 'bottle', true], ['Stout', 1200, 'bottle', true]],
            'Spirits' => [['House Whisky (shot)', 1500, 'glass', true], ['Vodka (shot)', 1400, 'glass', true]],
            'Soft Drinks' => [['Cola', 500, 'bottle', true], ['Water', 300, 'bottle', true]],
        ]);

        $this->menu($restaurant, [
            'Starters' => [['Pepper Soup', 2500, 'plate', false], ['Spring Rolls', 1800, 'plate', false]],
            'Mains' => [['Jollof Rice & Chicken', 3500, 'plate', false], ['Grilled Fish', 4500, 'plate', false]],
            'Desserts' => [['Ice Cream', 1500, 'plate', false]],
        ]);

        // A dedicated POS-staff user who can only operate the bar.
        $barman = User::query()->firstOrNew(['email' => 'barman@annshaven.com']);
        $barman->fill([
            'name' => 'Barman Joe',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        $barman->teamMemberships()->updateOrCreate(
            ['team_id' => $team->id],
            ['role' => TeamRole::Pos->value],
        );
        $barman->switchTeam($team);
        $barman->posOutlets()->syncWithoutDetaching([
            $bar->id => ['team_id' => $team->id],
        ]);

        // Register the barman in the Staff directory under the Bar department.
        $this->registerStaff($bar, $barman);
    }

    private function outlet(Team $team, string $name, string $type): PosOutlet
    {
        $department = Department::query()->firstOrCreate(
            ['team_id' => $team->id, 'name' => $name],
            ['description' => ucfirst($type).' point of sale', 'status' => 'active'],
        );

        $outlet = PosOutlet::query()->firstOrCreate(
            ['team_id' => $team->id, 'name' => $name],
            ['type' => $type, 'status' => 'active'],
        );

        if ($outlet->department_id !== $department->id) {
            $outlet->update(['department_id' => $department->id]);
        }

        return $outlet;
    }

    private function registerStaff(PosOutlet $outlet, User $user): void
    {
        Staff::query()->firstOrCreate(
            ['team_id' => $outlet->team_id, 'email' => $user->email],
            [
                'department_id' => $outlet->department_id,
                'full_name' => $user->name,
                'role' => $outlet->type === 'bar' ? 'bartender' : 'waiter',
                'employment_date' => now()->toDateString(),
                'status' => 'active',
            ],
        );
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: int|float, 2: string, 3: bool}>>  $menu
     */
    private function menu(PosOutlet $outlet, array $menu): void
    {
        foreach ($menu as $categoryName => $items) {
            $category = PosCategory::query()->firstOrCreate(
                ['pos_outlet_id' => $outlet->id, 'name' => $categoryName],
                ['team_id' => $outlet->team_id],
            );

            foreach ($items as [$name, $price, $unit, $tracksStock]) {
                $item = PosMenuItem::query()->firstOrCreate(
                    ['pos_outlet_id' => $outlet->id, 'name' => $name],
                    [
                        'team_id' => $outlet->team_id,
                        'pos_category_id' => $category->id,
                        'price' => $price,
                        'unit' => $unit,
                        'track_stock' => $tracksStock,
                        'is_active' => true,
                    ],
                );

                // Give tracked items an opening balance so the demo bar can sell.
                if ($tracksStock && $item->movements()->doesntExist()) {
                    app(PosInventoryService::class)->record($item, 'opening', 100, [
                        'notes' => 'Opening stock (seed)',
                    ]);
                }
            }
        }
    }
}
