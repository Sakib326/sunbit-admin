<?php

// filepath: app/Filament/Widgets/TopPackagesWidget.php

namespace App\Filament\Widgets;

use App\Models\TourPackage;
use App\Models\Booking;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPackagesWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Tour Packages';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return TourPackage::query()
            ->withCount(['bookings as bookings_count' => function ($query) {
                $query->where('service_type', 'TOURS')
                      ->where('created_at', '>=', now()->subDays(30));
            }])
            ->addSelect([
                'total_revenue' => Booking::selectRaw('COALESCE(SUM(final_amount), 0)')
                    ->whereColumn('tour_package_id', 'tour_packages.id')
                    ->where('service_type', 'TOURS')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->whereExists(function ($query) {
                        $query->select('id')
                              ->from('payments')
                              ->whereColumn('booking_id', 'bookings.id')
                              ->where('status', 'completed');
                    })
            ])
            ->orderBy('bookings_count', 'desc')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('title')
                ->label('Package')
                ->limit(40)
                ->searchable(),

            Tables\Columns\TextColumn::make('category.name')
                ->label('Category')
                ->badge(),

            Tables\Columns\TextColumn::make('number_of_days')
                ->label('Duration')
                ->formatStateUsing(fn ($state, $record) => $state . 'D/' . $record->number_of_nights . 'N')
                ->badge()
                ->color('info'),

            Tables\Columns\TextColumn::make('bookings_count')
                ->label('Bookings (30d)')
                ->badge()
                ->color('success')
                ->default(0),

            Tables\Columns\TextColumn::make('base_price_adult')
                ->label('Price')
                ->money('USD')
                ->sortable(),

            Tables\Columns\TextColumn::make('total_revenue')
                ->label('Revenue (30d)')
                ->money('USD')
                ->color('success')
                ->default(0),

            Tables\Columns\IconColumn::make('status')
                ->label('Active')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->getStateUsing(fn ($record) => $record->status === 'active'),
        ];
    }
}
