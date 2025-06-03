<?php

// filepath: app/Filament/Pages/FinancialReports.php

namespace App\Filament\Pages;

use App\Models\Booking;
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

class FinancialReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Financial Reports';
    protected static ?string $title = 'Financial Reports & Analytics';
    protected static string $view = 'filament.pages.unified-reports';
    protected static ?int $navigationSort = 3;

    public $dateFrom;
    public $dateTo;
    public $serviceType;
    public $paymentMethod;
    public $status;
    public $paymentType;

    public function mount(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
    }

    public function getStats(): array
    {
        // Payment based calculations (what actually happened)
        $paymentQuery = $this->getBaseQuery();
        $totalPaymentTransactions = $paymentQuery->sum('amount');
        $completedPaymentTransactions = $paymentQuery->where('status', 'completed')->sum('amount');
        $pendingPaymentTransactions = $paymentQuery->where('status', 'pending')->sum('amount');
        $failedPaymentTransactions = $paymentQuery->where('status', 'failed')->sum('amount');

        // Booking based calculations (what's actually owed)
        $bookingQuery = $this->getBookingBaseQuery();
        $totalBookingValue = $bookingQuery->sum('final_amount');
        $totalPaidFromBookings = $bookingQuery->sum('paid_amount');
        $totalPendingFromBookings = $bookingQuery->sum('due_amount');

        // Mixed calculation - more accurate
        $bookingIds = $bookingQuery->pluck('id');
        $actualCompletedPayments = Payment::whereIn('booking_id', $bookingIds)
            ->where('status', 'completed')
            ->when($this->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->sum('amount');

        return [
            Stat::make('Total Booking Value', '$' . number_format($totalBookingValue, 2))
                ->description('Total value of bookings in period')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Collected Amount', '$' . number_format($actualCompletedPayments, 2))
                ->description('Successfully collected payments')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Pending Collection', '$' . number_format($totalPendingFromBookings, 2))
                ->description('Outstanding amounts to collect')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Failed Payments', '$' . number_format($failedPaymentTransactions, 2))
                ->description('Failed payment attempts')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }

    private function getBaseQuery()
    {
        return Payment::query()
            ->when($this->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->when($this->serviceType, fn ($q) => $q->whereHas('booking', function ($query) {
                $query->where('service_type', $this->serviceType);
            }))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->paymentType, fn ($q) => $q->where('payment_type', $this->paymentType));
    }

    private function getBookingBaseQuery()
    {
        return Booking::query()
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->serviceType, fn ($q) => $q->where('service_type', $this->serviceType))
            ->whereNotIn('status', ['cancelled']);
    }

    public function getFilterOptions(): array
    {
        return [
            'serviceTypes' => [
                'TOURS' => 'Tours',
                'CAR_RENTAL' => 'Car Rental',
            ],
            'paymentMethods' => [
                'cash' => 'Cash',
                'card_terminal' => 'Card Terminal',
                'bank_transfer' => 'Bank Transfer',
                'bkash' => 'bKash',
                'nagad' => 'Nagad',
                'rocket' => 'Rocket',
                'stripe' => 'Stripe',
                'paypal' => 'PayPal',
            ],
            'statuses' => [
                'pending' => 'Pending',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                'refunded' => 'Refunded',
            ],
            'paymentTypes' => [
                'advance_payment' => 'Advance Payment',
                'partial_payment' => 'Partial Payment',
                'full_payment' => 'Full Payment',
                'refund' => 'Refund',
            ],
        ];
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
        $this->serviceType = null;
        $this->paymentMethod = null;
        $this->status = null;
        $this->paymentType = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getBaseQuery()->with(['booking.customer', 'booking.agent', 'booking.tourPackage', 'booking.carRentalPackage']))
            ->columns([
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Payment ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking.booking_reference')
                    ->label('Booking ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking.service_type')
                    ->label('Service')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TOURS' => 'primary',
                        'CAR_RENTAL' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('booking.customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking.final_amount')
                    ->label('Booking Value')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Payment Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking.due_amount')
                    ->label('Outstanding')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'refunded' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'advance_payment' => 'primary',
                        'partial_payment' => 'warning',
                        'full_payment' => 'success',
                        'refund' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function getReportType(): string
    {
        return 'financial';
    }
}
