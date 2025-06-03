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
        $lastMonth = Carbon::now()->subMonth();

        // TODAY'S BOOKINGS
        $todayBookings = Booking::whereDate('created_at', $today)->count();
        $yesterdayBookings = Booking::whereDate('created_at', $today->copy()->subDay())->count();
        $bookingChange = $yesterdayBookings > 0 ? (($todayBookings - $yesterdayBookings) / $yesterdayBookings) * 100 : ($todayBookings > 0 ? 100 : 0);

        // THIS MONTH'S REVENUE (completed payments only)
        $thisMonthRevenue = Payment::where('status', 'completed')
            ->whereDate('payment_date', '>=', $thisMonth)
            ->sum('amount');
        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereDate('payment_date', '>=', $lastMonth->startOfMonth())
            ->whereDate('payment_date', '<', $thisMonth)
            ->sum('amount');
        $revenueChange = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : ($thisMonthRevenue > 0 ? 100 : 0);

        // ACTIVE PACKAGES (current active)
        $activeTourPackages = TourPackage::where('status', 'active')->count();
        $activeCarPackages = CarRentalPackage::where('status', 'active')->count();

        // THIS MONTH'S PENDING PAYMENTS
        $thisMonthBookings = Booking::whereDate('created_at', '>=', $thisMonth)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $thisMonthTotalValue = $thisMonthBookings->sum('final_amount');
        $thisMonthPaidAmount = Payment::whereIn('booking_id', $thisMonthBookings->pluck('id'))
            ->where('status', 'completed')
            ->sum('amount');
        $thisMonthPending = max(0, $thisMonthTotalValue - $thisMonthPaidAmount);

        $pendingBookingsCount = $thisMonthBookings->whereIn('payment_status', ['pending', 'partial'])->count();

        return [
            Stat::make('Today Bookings', number_format($todayBookings))
                ->description($bookingChange >= 0 ? "+".number_format($bookingChange, 1)."% from yesterday" : number_format($bookingChange, 1)."% from yesterday")
                ->descriptionIcon($bookingChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($bookingChange >= 0 ? 'success' : 'danger')
                ->chart([7, 2, 10, 3, 15, 4, $todayBookings]),

            Stat::make('This Month Revenue', '$' . number_format($thisMonthRevenue, 2))
                ->description($revenueChange >= 0 ? "+".number_format($revenueChange, 1)."% from last month" : number_format($revenueChange, 1)."% from last month")
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('Active Packages', number_format($activeTourPackages + $activeCarPackages))
                ->description("Tours: {$activeTourPackages} | Cars: {$activeCarPackages}")
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('This Month Pending', '$' . number_format($thisMonthPending, 2))
                ->description("{$pendingBookingsCount} bookings this month")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($thisMonthPending > 1000 ? 'danger' : 'warning')
                ->url('/admin/financial-reports'),
        ];
    }

    protected static ?string $pollingInterval = '30s';
}
