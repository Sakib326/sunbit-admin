<?php

// filepath: app/Filament/Widgets/TopCarModelsWidget.php

namespace App\Filament\Widgets;

use App\Models\CarRentalPackage;
use App\Models\Booking;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopCarModelsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Car Rental Models';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return CarRentalPackage::query()
            ->withCount(['bookings as bookings_count' => function ($query) {
                $query->where('service_type', 'CAR_RENTAL')
                      ->where('created_at', '>=', now()->subDays(30));
            }])
            ->addSelect([
                'total_revenue' => Booking::selectRaw('COALESCE(SUM(final_amount), 0)')
                    ->whereColumn('car_rental_package_id', 'car_rental_packages.id')
                    ->where('service_type', 'CAR_RENTAL')
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
                ->label('Car Model')
                ->searchable()
                ->description(fn ($record) => "{$record->car_brand} - {$record->car_model}"),

            Tables\Columns\TextColumn::make('car_brand')
                ->label('Brand')
                ->badge(),

            Tables\Columns\TextColumn::make('pax_capacity')
                ->label('Capacity')
                ->badge()
                ->color('info')
                ->formatStateUsing(fn ($state) => $state . ' pax'),

            Tables\Columns\TextColumn::make('bookings_count')
                ->label('Bookings (30d)')
                ->badge()
                ->color('success')
                ->default(0),

            Tables\Columns\TextColumn::make('daily_price')
                ->label('Daily Rate')
                ->money('USD'),

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
