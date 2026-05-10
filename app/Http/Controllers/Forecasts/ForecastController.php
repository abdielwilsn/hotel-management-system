<?php

namespace App\Http\Controllers\Forecasts;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        abort_unless(
            $request->user()?->teamRole($current_team)?->isAtLeast(TeamRole::Admin),
            403,
        );

        $today = now()->startOfDay();
        $windowStart = $today->copy()->subDays(90);

        $historicalBookings = $current_team->bookings()
            ->whereBetween('check_in_date', [$windowStart, $today])
            ->count();

        $historicalBookingRevenue = (float) $current_team->bookings()
            ->whereBetween('check_in_date', [$windowStart, $today])
            ->sum('total_amount');

        $historicalOccupancy = (float) $current_team->rooms()->where('status', 'occupied')->count();
        $totalRooms = max($current_team->rooms()->count(), 1);
        $baseOccupancyRate = round(($historicalOccupancy / $totalRooms) * 100, 1);

        $upcomingBookings30 = $current_team->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->whereBetween('check_in_date', [$today, $today->copy()->addDays(30)->endOfDay()])
            ->count();

        $projectedOccupancyRate = min(
            100,
            round(($upcomingBookings30 / $totalRooms) * 100, 1)
        );

        $projectedRevenue30 = round(
            max($historicalBookings, 1) > 0
                ? ($historicalBookingRevenue / max($historicalBookings, 1)) * $upcomingBookings30
                : 0,
            2,
        );

        $outstandingRevenue = round((float) $current_team->invoices()
            ->where('status', '!=', 'void')
            ->get(['total_amount', 'paid_amount'])
            ->sum(fn ($invoice) => max((float) $invoice->total_amount - (float) $invoice->paid_amount, 0)), 2);

        $recentExpenses = round((float) $current_team->expenses()
            ->where('status', 'paid')
            ->whereBetween('incurred_date', [$today->copy()->subDays(30), $today])
            ->sum('amount'), 2);

        $projectedNetProfit30 = round($projectedRevenue30 - $recentExpenses, 2);

        $alerts = [];

        if ($projectedOccupancyRate < 60) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Occupancy risk',
                'message' => 'Projected occupancy for the next 30 days is below 60%.',
            ];
        }

        if ($outstandingRevenue > ($projectedRevenue30 * 0.5)) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Collection risk',
                'message' => 'Outstanding invoice balance is above 50% of projected revenue.',
            ];
        }

        if ($projectedNetProfit30 < 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Profitability risk',
                'message' => 'Projected net profit is negative based on recent paid expenses.',
            ];
        }

        return Inertia::render('forecasts/Index', [
            'forecast' => [
                'projected_occupancy_rate' => $projectedOccupancyRate,
                'baseline_occupancy_rate' => $baseOccupancyRate,
                'projected_revenue_30_days' => $projectedRevenue30,
                'projected_net_profit_30_days' => $projectedNetProfit30,
                'outstanding_revenue' => $outstandingRevenue,
                'recent_expenses_30_days' => $recentExpenses,
                'upcoming_bookings_30_days' => $upcomingBookings30,
                'total_rooms' => $totalRooms,
            ],
            'alerts' => $alerts,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }
}
