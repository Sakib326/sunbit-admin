<?php

// filepath: app/Filament/Widgets/RevenueChart.php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue Overview';
    protected static ?int $sort = 3;

    public ?string $filter = 'month';

    protected function getFilters(): ?array
    {
        return [
            'week' => 'This week',
            'month' => 'This month',
            'quarter' => 'This quarter',
            'year' => 'This year',
        ];
    }

    protected function getData(): array
    {
        $period = match ($this->filter) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        $payments = Payment::where('status', 'completed')
            ->where('created_at', '>=', $period)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dates = collect();
        $current = $period->copy();
        while ($current <= Carbon::now()) {
            $dates->push($current->format('Y-m-d'));
            $current->addDay();
        }

        $data = $dates->map(function ($date) use ($payments) {
            return $payments->where('date', $date)->sum('total');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->toArray(),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                    ],
                ],
            ],
            'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format('M d'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
