<?php

// filepath: app/Filament/Pages/TourReports.php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\Payment;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TourReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Tour Reports';
    protected static ?string $title = 'Tour Reports & Analytics';
    protected static string $view = 'filament.pages.unified-reports';
    protected static ?int $navigationSort = 1;

    public $dateFrom;
    public $dateTo;
    public $packageId;
    public $agentId;
    public $status;
    public $paymentStatus;

    public function mount(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
    }

    public function getStats(): array
    {
        $query = $this->getBaseQuery();

        $totalBookings = $query->count();
        $totalRevenue = $query->sum('final_amount');

        // Paid revenue from actual payments
        $bookingIds = $query->pluck('id');
        $paidRevenue = Payment::whereIn('booking_id', $bookingIds)
            ->where('status', 'completed')
            ->sum('amount');

        // Pending revenue = Total - Paid
        $pendingRevenue = max(0, $totalRevenue - $paidRevenue);

        // Average booking value
        $avgBookingValue = $totalBookings > 0 ? $totalRevenue / $totalBookings : 0;

        return [
            Stat::make('Total Bookings', number_format($totalBookings))
                ->description('Tour bookings in selected period')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Total Revenue', '$' . number_format($totalRevenue, 2))
                ->description('Total booking value')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Paid Revenue', '$' . number_format($paidRevenue, 2))
                ->description('Successfully collected')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Pending Revenue', '$' . number_format($pendingRevenue, 2))
                ->description('Awaiting payment')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }

    private function getBaseQuery()
    {
        return Booking::where('service_type', 'TOURS')
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->packageId, fn ($q) => $q->where('tour_package_id', $this->packageId))
            ->when($this->agentId, fn ($q) => $q->where('agent_id', $this->agentId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->paymentStatus, fn ($q) => $q->where('payment_status', $this->paymentStatus));
    }

    public function getFilterOptions(): array
    {
        return [
            'packages' => TourPackage::where('status', 'active')->pluck('title', 'id')->toArray(),
            'agents' => User::where('role', 'agent')->pluck('name', 'id')->toArray(),
            'statuses' => [
                'draft' => 'Draft',
                'confirmed' => 'Confirmed',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            'paymentStatuses' => [
                'pending' => 'Pending',
                'partial' => 'Partial',
                'paid' => 'Paid',
                'refunded' => 'Refunded',
            ],
        ];
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
        $this->packageId = null;
        $this->agentId = null;
        $this->status = null;
        $this->paymentStatus = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getBaseQuery()->with(['tourPackage.category', 'customer', 'agent', 'payments']))
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Booking ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tourPackage.title')
                    ->label('Tour Package')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->tourPackage?->title)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agent')
                    ->default('Direct')
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_date')
                    ->label('Tour Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pax_info')
                    ->label('Passengers')
                    ->getStateUsing(fn ($record) => $record->adults . 'A + ' . $record->children . 'C')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Total Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_amount')
                    ->label('Due Amount')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'confirmed' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'partial' => 'info',
                        'paid' => 'success',
                        'refunded' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Booked On')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function getReportType(): string
    {
        return 'tours';
    }
}
