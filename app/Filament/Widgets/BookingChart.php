<?php

// filepath: app/Filament/Widgets/BookingChart.php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class BookingChart extends ChartWidget
{
    protected static ?string $heading = 'Booking Trends';
    protected static ?int $sort = 2;

    public ?string $filter = '7days';

    protected function getFilters(): ?array
    {
        return [
            '7days' => 'Last 7 days',
            '30days' => 'Last 30 days',
            '90days' => 'Last 90 days',
            'year' => 'This year',
        ];
    }

    protected function getData(): array
    {
        $days = match ($this->filter) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            'year' => 365,
            default => 7,
        };

        $bookings = Booking::selectRaw('DATE(created_at) as date, service_type, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('date', 'service_type')
            ->orderBy('date')
            ->get();

        $dates = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates->push(Carbon::now()->subDays($i)->format('Y-m-d'));
        }

        $tourData = [];
        $carData = [];

        foreach ($dates as $date) {
            $tourBookings = $bookings->where('date', $date)->where('service_type', 'TOURS')->sum('count');
            $carBookings = $bookings->where('date', $date)->where('service_type', 'CAR_RENTAL')->sum('count');

            $tourData[] = $tourBookings;
            $carData[] = $carBookings;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tour Bookings',
                    'data' => $tourData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Car Rental Bookings',
                    'data' => $carData,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format('M d'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
