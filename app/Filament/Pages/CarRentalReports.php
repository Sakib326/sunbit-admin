<?php

// filepath: app/Filament/Pages/CarRentalReports.php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\CarRentalPackage;
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

class CarRentalReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Car Rental Reports';
    protected static ?string $title = 'Car Rental Reports & Analytics';
    protected static string $view = 'filament.pages.unified-reports';
    protected static ?int $navigationSort = 2;

    public $dateFrom;
    public $dateTo;
    public $packageId;
    public $agentId;
    public $status;
    public $paymentStatus;
    public $carBrand;

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

        return [
            Stat::make('Total Bookings', number_format($totalBookings))
                ->description('Car rental bookings')
                ->descriptionIcon('heroicon-m-truck')
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
        return Booking::where('service_type', 'CAR_RENTAL')
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->packageId, fn ($q) => $q->where('car_rental_package_id', $this->packageId))
            ->when($this->agentId, fn ($q) => $q->where('agent_id', $this->agentId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->paymentStatus, fn ($q) => $q->where('payment_status', $this->paymentStatus))
            ->when($this->carBrand, fn ($q) => $q->whereHas('carRentalPackage', function ($query) {
                $query->where('car_brand', $this->carBrand);
            }));
    }

    public function getFilterOptions(): array
    {
        return [
            'packages' => CarRentalPackage::where('status', 'active')->pluck('title', 'id')->toArray(),
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
            'carBrands' => CarRentalPackage::distinct('car_brand')->pluck('car_brand', 'car_brand')->toArray(),
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
        $this->carBrand = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getBaseQuery()->with(['carRentalPackage', 'carRentalDetails', 'customer', 'agent', 'payments']))
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Booking ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('carRentalPackage.title')
                    ->label('Car Model')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->carRentalPackage?->title)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('carRentalPackage.car_brand')
                    ->label('Brand')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agent')
                    ->default('Direct')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rental_period')
                    ->label('Rental Period')
                    ->getStateUsing(function ($record) {
                        $details = $record->carRentalDetails;
                        if (!$details) {
                            return 'N/A';
                        }
                        return $details->pickup_date->format('M j') . ' - ' . $details->return_date->format('M j, Y') . ' (' . $details->rental_days . ' days)';
                    }),

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
        return 'car_rental';
    }
}
