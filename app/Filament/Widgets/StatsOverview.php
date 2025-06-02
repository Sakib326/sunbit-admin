<?php

// filepath: app/Filament/Widgets/StatsOverview.php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\CarRentalPackage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Total bookings today
        $todayBookings = Booking::whereDate('created_at', $today)->count();
        $yesterdayBookings = Booking::whereDate('created_at', $today->copy()->subDay())->count();
        $bookingChange = $yesterdayBookings > 0 ? (($todayBookings - $yesterdayBookings) / $yesterdayBookings) * 100 : 100;

        // Revenue this month
        $thisMonthRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', '>=', $thisMonth)
            ->sum('amount');
        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $thisMonth])
            ->sum('amount');
        $revenueChange = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 100;

        // Active packages
        $activeTourPackages = TourPackage::where('status', 'active')->count();
        $activeCarPackages = CarRentalPackage::where('status', 'active')->count();

        // Pending payments
        $pendingPayments = Payment::where('status', 'pending')->sum('amount');

        return [
            Stat::make('Today Bookings', $todayBookings)
                ->description($bookingChange >= 0 ? "+{$bookingChange}% from yesterday" : "{$bookingChange}% from yesterday")
                ->descriptionIcon($bookingChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($bookingChange >= 0 ? 'success' : 'danger')
                ->chart([7, 2, 10, 3, 15, 4, $todayBookings]),

            Stat::make('This Month Revenue', '$' . number_format($thisMonthRevenue, 2))
                ->description($revenueChange >= 0 ? "+{$revenueChange}% from last month" : "{$revenueChange}% from last month")
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('Active Packages', $activeTourPackages + $activeCarPackages)
                ->description("Tours: {$activeTourPackages} | Cars: {$activeCarPackages}")
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Pending Payments', '$' . number_format($pendingPayments, 2))
                ->description('Requires attention')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
