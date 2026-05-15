<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Team $current_team): Response
    {
        $today = now();

        $currentWeekStart = $today->copy()->startOfWeek();
        $currentWeekEnd = $today->copy()->endOfWeek();
        $previousWeekStart = $today->copy()->subWeek()->startOfWeek();
        $previousWeekEnd = $today->copy()->subWeek()->endOfWeek();

        $newBookings = $current_team->bookings()
            ->whereBetween('created_at', [$currentWeekStart, $currentWeekEnd])
            ->count();

        $newBookingsPrevious = $current_team->bookings()
            ->whereBetween('created_at', [$previousWeekStart, $previousWeekEnd])
            ->count();

        $checkInsThisWeek = $current_team->bookings()
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$currentWeekStart->toDateString(), $currentWeekEnd->toDateString()])
            ->count();

        $checkInsPreviousWeek = $current_team->bookings()
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$previousWeekStart->toDateString(), $previousWeekEnd->toDateString()])
            ->count();

        $checkOutsToday = $current_team->bookings()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_out_date', $today->toDateString())
            ->count();

        $checkOutsYesterday = $current_team->bookings()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_out_date', $today->copy()->subDay()->toDateString())
            ->count();

        $currentMonthStart = $today->copy()->startOfMonth();
        $daysElapsed = (int) $today->day;

        $previousMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $previousComparableEnd = $previousMonthStart->copy()->addDays(max($daysElapsed - 1, 0))->endOfDay();

        $revenueMtd = (float) $current_team->invoices()
            ->where('status', '!=', 'void')
            ->whereBetween('created_at', [$currentMonthStart->startOfDay(), $today->copy()->endOfDay()])
            ->sum('total_amount');

        $revenuePreviousMtd = (float) $current_team->invoices()
            ->where('status', '!=', 'void')
            ->whereBetween('created_at', [$previousMonthStart->startOfDay(), $previousComparableEnd])
            ->sum('total_amount');

        return Inertia::render('Dashboard', [
            'metricCards' => [
                [
                    'title' => 'New Bookings',
                    'value' => $newBookings,
                    'delta' => $this->delta($newBookings, $newBookingsPrevious),
                    'hint' => 'vs last week',
                ],
                [
                    'title' => 'Current Check-ins',
                    'value' => $checkInsThisWeek,
                    'delta' => $this->delta($checkInsThisWeek, $checkInsPreviousWeek),
                    'hint' => 'check-ins this week',
                ],
                [
                    'title' => 'Check-outs Today',
                    'value' => $checkOutsToday,
                    'delta' => $this->delta($checkOutsToday, $checkOutsYesterday),
                    'hint' => 'expected departures',
                ],
                [
                    'title' => 'Revenue (MTD)',
                    'value' => round($revenueMtd, 2),
                    'delta' => $this->delta($revenueMtd, $revenuePreviousMtd),
                    'hint' => 'compared to prior month pace',
                    'currency' => true,
                ],
            ],
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    private function delta(float|int $current, float|int $previous): string
    {
        $currentValue = (float) $current;
        $previousValue = (float) $previous;

        if ($previousValue == 0.0) {
            if ($currentValue == 0.0) {
                return '+0.0%';
            }

            return '+100.0%';
        }

        $change = (($currentValue - $previousValue) / $previousValue) * 100;

        return sprintf('%s%.1f%%', $change >= 0 ? '+' : '', $change);
    }
}
