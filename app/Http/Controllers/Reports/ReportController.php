<?php

namespace App\Http\Controllers\Reports;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        abort_unless(
            $request->user()?->hasAbility(Ability::ViewReports, $current_team),
            403,
        );

        $totalRooms = $current_team->rooms()->count();
        $occupiedRooms = $current_team->rooms()->where('status', 'occupied')->count();
        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100, 1)
            : 0;

        $activeBookings = $current_team->bookings()->active()->count();
        $bookingsThisMonth = $current_team->bookings()
            ->whereBetween('check_in_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $grossRevenue = (float) $current_team->invoices()
            ->where('status', '!=', 'void')
            ->sum('total_amount');

        $collectedRevenue = (float) $current_team->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $outstandingRevenue = round(max($grossRevenue - $collectedRevenue, 0), 2);

        $paidExpenses = (float) $current_team->expenses()
            ->where('status', 'paid')
            ->sum('amount');

        $netProfit = round($collectedRevenue - $paidExpenses, 2);

        $averageDailyRate = round((float) $current_team->bookings()->avg('price_per_night'), 2);

        $upcomingCheckIns = $current_team->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('check_in_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->count();

        $upcomingCheckOuts = $current_team->bookings()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereBetween('check_out_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->count();

        $monthlyTrend = collect(range(5, 0))->map(function (int $monthsAgo) use ($current_team): array {
            $start = now()->subMonths($monthsAgo)->startOfMonth();
            $end = now()->subMonths($monthsAgo)->endOfMonth();

            $invoiced = (float) $current_team->invoices()
                ->where('status', '!=', 'void')
                ->whereBetween('issue_date', [$start, $end])
                ->sum('total_amount');

            $collected = (float) $current_team->payments()
                ->where('status', 'completed')
                ->whereBetween('payment_date', [$start, $end])
                ->sum('amount');

            return [
                'label' => $start->format('M'),
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
            ];
        })->values();

        $paymentMethods = $current_team->payments()
            ->where('status', 'completed')
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) $row->method,
                'total' => round((float) $row->total, 2),
            ])
            ->values();

        return Inertia::render('reports/Index', [
            'summary' => [
                'occupancy_rate' => $occupancyRate,
                'active_bookings' => $activeBookings,
                'bookings_this_month' => $bookingsThisMonth,
                'gross_revenue' => round($grossRevenue, 2),
                'collected_revenue' => round($collectedRevenue, 2),
                'outstanding_revenue' => $outstandingRevenue,
                'paid_expenses' => round($paidExpenses, 2),
                'net_profit' => $netProfit,
                'average_daily_rate' => $averageDailyRate,
                'upcoming_check_ins' => $upcomingCheckIns,
                'upcoming_check_outs' => $upcomingCheckOuts,
                'occupied_rooms' => $occupiedRooms,
                'total_rooms' => $totalRooms,
            ],
            'monthlyTrend' => $monthlyTrend,
            'paymentMethods' => $paymentMethods,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }
}
