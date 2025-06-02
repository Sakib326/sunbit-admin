<?php

// filepath: app/Filament/Resources/PaymentResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model; // Add this import

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'payment_reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // Step 1: Booking Selection
                    Forms\Components\Wizard\Step::make('Booking Selection')
                        ->schema([
                            Forms\Components\Select::make('booking_id')
                                ->label('Select Booking')
                                ->relationship('booking', 'booking_reference')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $booking = Booking::find($state);
                                        if ($booking) {
                                            $set('amount', $booking->due_amount);
                                            $set('currency', 'BDT');
                                            $set('payer_type', 'customer');
                                            $set('payer_id', $booking->customer_id);
                                        }
                                    }
                                })
                                ->getOptionLabelFromRecordUsing(function ($record) {
                                    return "{$record->booking_reference} - {$record->customer_name} - Due: ৳" . number_format($record->due_amount, 2);
                                }),

                            // Booking Summary Card
                            Forms\Components\Placeholder::make('booking_summary')
                                ->label('Booking Summary')
                                ->content(function (Forms\Get $get) {
                                    if (!$get('booking_id')) {
                                        return 'Select a booking to see details';
                                    }

                                    $booking = Booking::find($get('booking_id'));
                                    if (!$booking) {
                                        return 'Booking not found';
                                    }

                                    return new \Illuminate\Support\HtmlString("
                                        <div class='p-4 bg-gray-50 rounded-lg'>
                                            <div class='grid grid-cols-2 gap-4'>
                                                <div>
                                                    <strong>Customer:</strong> {$booking->customer_name}<br>
                                                    <strong>Service:</strong> {$booking->service_type}<br>
                                                    <strong>Date:</strong> " . $booking->service_date->format('d M Y') . "
                                                </div>
                                                <div>
                                                    <strong>Total Amount:</strong> ৳" . number_format($booking->final_amount, 2) . "<br>
                                                    <strong>Paid Amount:</strong> ৳" . number_format($booking->paid_amount, 2) . "<br>
                                                    <strong>Due Amount:</strong> ৳" . number_format($booking->due_amount, 2) . "
                                                </div>
                                            </div>
                                        </div>
                                    ");
                                })
                                ->visible(fn (Forms\Get $get) => $get('booking_id')),
                        ]),

                    // Step 2: Payment Details
                    Forms\Components\Wizard\Step::make('Payment Details')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('amount')
                                        ->label('Payment Amount')
                                        ->required()
                                        ->numeric()
                                        ->prefix('৳')
                                        ->minValue(0.01)
                                        ->live()
                                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                            if ($get('booking_id') && $state) {
                                                $booking = Booking::find($get('booking_id'));
                                                if ($booking && $state > $booking->due_amount) {
                                                    $set('amount', $booking->due_amount);
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('Amount Adjusted')
                                                        ->body('Payment amount cannot exceed due amount.')
                                                        ->send();
                                                }
                                            }
                                        }),

                                    Forms\Components\Select::make('currency')
                                        ->label('Currency')
                                        ->options([
                                            'BDT' => 'BDT (Bangladeshi Taka)',
                                            'USD' => 'USD (US Dollar)',
                                            'EUR' => 'EUR (Euro)',
                                        ])
                                        ->default('BDT')
                                        ->required(),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('payment_method')
                                        ->label('Payment Method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'card' => 'Credit/Debit Card',
                                            'bank_transfer' => 'Bank Transfer',
                                            'mobile_banking' => 'Mobile Banking (bKash/Rocket/Nagad)',
                                            'cheque' => 'Cheque',
                                            'online_gateway' => 'Online Payment Gateway',
                                            'wallet' => 'Digital Wallet',
                                        ])
                                        ->required()
                                        ->live(),

                                    Forms\Components\Select::make('payment_type')
                                        ->label('Payment Type')
                                        ->options([
                                            'full_payment' => 'Full Payment',
                                            'partial_payment' => 'Partial Payment',
                                            'advance_payment' => 'Advance Payment',
                                            'refund' => 'Refund',
                                        ])
                                        ->default('partial_payment')
                                        ->required(),
                                ]),

                            Forms\Components\DateTimePicker::make('payment_date')
                                ->label('Payment Date & Time')
                                ->required()
                                ->default(now())
                                ->maxDate(now()),
                        ]),

                    // Step 3: Transaction Details
                    Forms\Components\Wizard\Step::make('Transaction Details')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('gateway_transaction_id')
                                        ->label('Gateway Transaction ID')
                                        ->maxLength(255)
                                        ->placeholder('Transaction ID from payment gateway')
                                        ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['card', 'online_gateway', 'mobile_banking'])),

                                    Forms\Components\TextInput::make('gateway_reference')
                                        ->label('Gateway Reference')
                                        ->maxLength(255)
                                        ->placeholder('Reference from payment gateway')
                                        ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['card', 'online_gateway', 'mobile_banking'])),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('receipt_number')
                                        ->label('Receipt Number')
                                        ->maxLength(255)
                                        ->placeholder('Physical receipt number'),

                                    Forms\Components\TextInput::make('terminal_id')
                                        ->label('Terminal ID')
                                        ->maxLength(255)
                                        ->placeholder('POS terminal ID')
                                        ->visible(fn (Forms\Get $get) => $get('payment_method') === 'card'),
                                ]),

                            Forms\Components\Select::make('status')
                                ->label('Payment Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'processing' => 'Processing',
                                    'completed' => 'Completed',
                                    'failed' => 'Failed',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->default('completed')
                                ->required(),

                            Forms\Components\Textarea::make('notes')
                                ->label('Payment Notes')
                                ->rows(3)
                                ->placeholder('Additional notes about this payment'),
                        ]),

                    // Step 4: Admin Controls (Optional)
                    Forms\Components\Wizard\Step::make('Admin Controls')
                        ->schema([
                            Forms\Components\Toggle::make('admin_override')
                                ->label('Admin Override')
                                ->helperText('Check if this payment requires special handling')
                                ->live(),

                            Forms\Components\Textarea::make('override_reason')
                                ->label('Override Reason')
                                ->required()
                                ->visible(fn (Forms\Get $get) => $get('admin_override'))
                                ->placeholder('Explain why this payment requires admin override'),

                            Forms\Components\Hidden::make('processed_by')
                                ->default(fn () => auth()->id()),

                            Forms\Components\Hidden::make('payer_type')
                                ->default('customer'),

                            Forms\Components\Hidden::make('payer_id'),
                        ])
                        ->visible(fn () => auth()->user()->role === 'admin'),
                ])
                ->columnSpanFull()
                ->skippable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Payment Ref')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('booking.booking_reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('booking.customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => $record->currency . ' ' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Method')
                    ->colors([
                        'success' => 'cash',
                        'primary' => 'card',
                        'warning' => 'bank_transfer',
                        'info' => 'mobile_banking',
                        'secondary' => 'cheque',
                    ]),

                Tables\Columns\BadgeColumn::make('payment_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'full_payment',
                        'warning' => 'partial_payment',
                        'info' => 'advance_payment',
                        'danger' => 'refund',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'info' => 'processing',
                        'danger' => 'failed',
                        'secondary' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('processedBy.name')
                    ->label('Processed By')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('admin_override')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Credit/Debit Card',
                        'bank_transfer' => 'Bank Transfer',
                        'mobile_banking' => 'Mobile Banking',
                        'cheque' => 'Cheque',
                        'online_gateway' => 'Online Gateway',
                        'wallet' => 'Digital Wallet',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('payment_type')
                    ->options([
                        'full_payment' => 'Full Payment',
                        'partial_payment' => 'Partial Payment',
                        'advance_payment' => 'Advance Payment',
                        'refund' => 'Refund',
                    ]),

                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->numeric()
                            ->prefix('৳'),
                        Forms\Components\TextInput::make('max_amount')
                            ->numeric()
                            ->prefix('৳'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),

                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->markAsCompleted();

                        Notification::make()
                            ->success()
                            ->title('Payment Completed')
                            ->body('Payment has been marked as completed and booking status updated.')
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel_payment')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->canBeCancelled())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'cancelled']);

                        Notification::make()
                            ->success()
                            ->title('Payment Cancelled')
                            ->body('Payment has been cancelled.')
                            ->send();
                    }),

                Tables\Actions\Action::make('view_booking')
                    ->label('View Booking')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => BookingResource::getUrl('view', ['record' => $record->booking_id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark Selected as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->markAsCompleted();
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Payments Updated')
                                ->body("{$count} payments marked as completed.")
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Payment Information')
                    ->schema([
                        Components\TextEntry::make('payment_reference')
                            ->label('Payment Reference')
                            ->copyable(),
                        Components\TextEntry::make('booking.booking_reference')
                            ->label('Booking Reference')
                            ->copyable(),
                        Components\TextEntry::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state, $record) => $record->currency . ' ' . number_format($state, 2)),
                        Components\TextEntry::make('payment_method')
                            ->badge(),
                        Components\TextEntry::make('payment_type')
                            ->badge(),
                        Components\TextEntry::make('status')
                            ->badge(),
                        Components\TextEntry::make('payment_date')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Components\Section::make('Booking Details')
                    ->schema([
                        Components\TextEntry::make('booking.customer_name'),
                        Components\TextEntry::make('booking.service_type')
                            ->badge(),
                        Components\TextEntry::make('booking.final_amount')
                            ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2)),
                        Components\TextEntry::make('booking.paid_amount')
                            ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2)),
                        Components\TextEntry::make('booking.due_amount')
                            ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2)),
                    ])
                    ->columns(3),

                Components\Section::make('Transaction Details')
                    ->schema([
                        Components\TextEntry::make('gateway_transaction_id')
                            ->visible(fn ($record) => !empty($record->gateway_transaction_id)),
                        Components\TextEntry::make('gateway_reference')
                            ->visible(fn ($record) => !empty($record->gateway_reference)),
                        Components\TextEntry::make('receipt_number')
                            ->visible(fn ($record) => !empty($record->receipt_number)),
                        Components\TextEntry::make('terminal_id')
                            ->visible(fn ($record) => !empty($record->terminal_id)),
                        Components\TextEntry::make('processedBy.name')
                            ->label('Processed By'),
                    ])
                    ->columns(3),

                Components\Section::make('Additional Information')
                    ->schema([
                        Components\TextEntry::make('notes')
                            ->visible(fn ($record) => !empty($record->notes))
                            ->columnSpanFull(),
                        Components\TextEntry::make('admin_override')
                            ->badge()
                            ->visible(fn ($record) => $record->admin_override),
                        Components\TextEntry::make('override_reason')
                            ->visible(fn ($record) => !empty($record->override_reason))
                            ->columnSpanFull(),
                        Components\TextEntry::make('failure_reason')
                            ->visible(fn ($record) => !empty($record->failure_reason))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : null;
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->payment_reference;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Booking' => $record->booking->booking_reference,
            'Customer' => $record->booking->customer_name,
            'Amount' => $record->currency . ' ' . number_format($record->amount, 2),
        ];
    }
}
