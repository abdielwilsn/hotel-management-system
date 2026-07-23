<?php

namespace App\Support;

use App\Models\Team;
use Carbon\Carbon;

/**
 * The occupancy/revenue/profitability projection shown on the Forecasts page,
 * extracted so the same numbers can also drive a periodic digest notification
 * without duplicating the queries behind them.
 */
class ForecastService
{
    /**
     * @return array{forecast: array<string, int|float>, alerts: array<int, array{level: string, title: string, message: string}>}
     */
    public function compute(Team $team): array
    {
        $today = Carbon::now()->startOfDay();
        $windowStart = $today->copy()->subDays(90);

        $historicalBookings = $team->bookings()
            ->whereBetween('check_in_date', [$windowStart, $today])
            ->count();

        $historicalBookingRevenue = (float) $team->bookings()
            ->whereBetween('check_in_date', [$windowStart, $today])
            ->sum('total_amount');

        $historicalOccupancy = (float) $team->rooms()->where('status', 'occupied')->count();
        $totalRooms = max($team->rooms()->count(), 1);
        $baseOccupancyRate = round(($historicalOccupancy / $totalRooms) * 100, 1);

        $upcomingBookings30 = $team->bookings()
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

        $outstandingRevenue = round((float) $team->invoices()
            ->where('status', '!=', 'void')
            ->get(['total_amount', 'paid_amount'])
            ->sum(fn ($invoice) => max((float) $invoice->total_amount - (float) $invoice->paid_amount, 0)), 2);

        $recentExpenses = round((float) $team->expenses()
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

        return [
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
        ];
    }

    /**
     * @return array<int, array{level: string, title: string, message: string}>
     */
    public function alertsFor(Team $team): array
    {
        return $this->compute($team)['alerts'];
    }
}
