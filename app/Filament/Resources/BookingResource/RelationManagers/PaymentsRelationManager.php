<?php

// filepath: app/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Booking;
use App\Models\Payment;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $recordTitleAttribute = 'payment_reference';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('booking_id')
                    ->default(fn () => $this->ownerRecord->id),

                Forms\Components\Select::make('payment_type')
                    ->options([
                        'initial' => 'Initial Payment',
                        'partial' => 'Partial Payment',
                        'final' => 'Final Payment',
                        'refund' => 'Refund',
                        'security_deposit' => 'Security Deposit'
                    ])
                    ->default('partial')
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('Payment Amount')
                    ->required()
                    ->numeric()
                    ->prefix('৳')
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $booking = $this->ownerRecord;
                        if ($booking && !$booking->allow_partial_payment) {
                            // Force full payment if partial payment is not allowed
                            if (floatval($state) < $booking->due_amount) {
                                $set('amount', $booking->due_amount);

                                Notification::make()
                                    ->warning()
                                    ->title('Full Payment Required')
                                    ->body("This booking requires full payment of ৳" . number_format($booking->due_amount, 2))
                                    ->send();
                            }
                        }
                    })
                    ->maxValue(fn () => $this->ownerRecord->due_amount)
                    ->helperText(fn () => "Maximum allowed: ৳" . number_format($this->ownerRecord->due_amount, 2)),

                Forms\Components\Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card_terminal' => 'Card Terminal',
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'rocket' => 'Rocket',
                        'bank_transfer' => 'Bank Transfer',
                        'online' => 'Online Payment',
                        'cheque' => 'Cheque'
                    ])
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled'
                    ])
                    ->default('completed')
                    ->required(),

                Forms\Components\TextInput::make('gateway_transaction_id')
                    ->label('Transaction ID')
                    ->maxLength(255),

                Forms\Components\Textarea::make('notes')
                    ->rows(2),

                Forms\Components\Hidden::make('processed_by')
                    ->default(fn () => auth()->id()),

                Forms\Components\Hidden::make('payment_date')
                    ->default(fn () => now()),

                Forms\Components\Hidden::make('currency')
                    ->default('BDT'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_reference')
            ->columns([
                Tables\Columns\TextColumn::make('payment_reference')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->colors([
                        'success' => 'cash',
                        'info' => ['bkash', 'nagad', 'rocket'],
                        'warning' => 'card_terminal',
                        'primary' => 'bank_transfer',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => ['pending', 'processing'],
                        'danger' => ['failed', 'cancelled'],
                    ]),

                Tables\Columns\TextColumn::make('payment_date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('processedBy.name')
                    ->label('Processed By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method'),
                Tables\Filters\SelectFilter::make('status'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['currency'] = 'BDT';
                        return $data;
                    })
                    ->after(function ($record) {
                        if ($record->status === 'completed') {
                            $record->markAsCompleted();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('mark_completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->markAsCompleted()),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
