<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountryResource\Pages;
use App\Filament\Resources\CountryResource\RelationManagers;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Services\CurrencyConverterService;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Location Management';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Country Information')
                    ->description('Basic details about the country')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $state, Forms\Set $set) =>
                                        $set('slug', Str::slug($state))),

                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\TextInput::make('code')
                                    ->label('ISO Code')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(3)
                                    ->helperText('ISO 3166-1 alpha-2/3 country code')
                                    ->formatStateUsing(fn ($state) => Str::upper($state))
                                    ->placeholder('e.g. BD, MY, US'),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])->columns(2),
                    ]),

                Section::make('Currency Information')
                    ->description('Currency details used for pricing and transactions')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('currency')
                                    ->label('Currency Name')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Bangladeshi Taka, US Dollar')
                                    ->helperText('Full currency name'),

                                Forms\Components\TextInput::make('currency_symbol')
                                    ->label('Currency Symbol')
                                    ->maxLength(10)
                                    ->placeholder('e.g. ৳, $, €, £')
                                    ->helperText('Currency symbol used in display'),
                            ])->columns(2),
                    ]),

                Section::make('Exchange Rates')
                    ->description('Currency conversion rates for international pricing')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('myr_exchange_rate')
                                    ->label('Rate to Malaysian Ringgit (MYR)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->default(1.0000)
                                    ->required()
                                    ->minValue(0.0001)
                                    ->maxValue(9999.9999)
                                    ->placeholder('1.0000')
                                    ->helperText('How many MYR for 1 unit of this currency (e.g., 1 BDT = 0.0380 MYR)')
                                    ->suffixIcon('heroicon-m-currency-dollar')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('usd_exchange_rate')
                                    ->label('Rate to US Dollar (USD)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->minValue(0.0001)
                                    ->maxValue(9999.9999)
                                    ->placeholder('0.0091')
                                    ->helperText('How many USD for 1 unit of this currency (e.g., 1 BDT = 0.0091 USD)')
                                    ->suffixIcon('heroicon-m-currency-dollar')
                                    ->columnSpan(1),

                                Forms\Components\DateTimePicker::make('exchange_rate_updated_at')
                                    ->label('Exchange Rate Last Updated')
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('M d, Y H:i')
                                    ->helperText('When these exchange rates were last updated')
                                    ->suffixIcon('heroicon-m-clock')
                                    ->columnSpan(2),
                            ])->columns(2),

                        // Exchange Rate Helper Section
                        Forms\Components\Placeholder::make('exchange_rate_helper')
                            ->label('Exchange Rate Calculator')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-3">
                                    <div class="flex items-center text-blue-700">
                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        <strong>How to Calculate Exchange Rates:</strong>
                                    </div>
                                    <div class="text-sm text-gray-700 space-y-2">
                                        <p><strong>MYR Rate Example:</strong> If 1 USD = 4.70 MYR and 1 USD = 110 BDT, then 1 BDT = 4.70/110 = 0.0427 MYR</p>
                                        <p><strong>USD Rate Example:</strong> If 1 USD = 110 BDT, then 1 BDT = 1/110 = 0.0091 USD</p>
                                        <p><strong>For Malaysia:</strong> Set MYR rate to 1.0000 (base currency)</p>
                                    </div>
                                </div>
                            ')),

                        // Currency Conversion Preview
                        Forms\Components\Group::make([
                            Forms\Components\Placeholder::make('conversion_preview')
                                ->label('Conversion Preview')
                                ->content(function ($get) {
                                    $currency = $get('currency') ?: 'Local Currency';
                                    $symbol = $get('currency_symbol') ?: '¤';
                                    $myrRate = $get('myr_exchange_rate') ?: 0;
                                    $usdRate = $get('usd_exchange_rate') ?: 0;

                                    if ($myrRate <= 0) {
                                        return new \Illuminate\Support\HtmlString('<div class="text-gray-500">Enter exchange rates to see preview</div>');
                                    }

                                    $previews = [
                                        "100 {$symbol} = RM " . number_format(100 * $myrRate, 2),
                                        "500 {$symbol} = RM " . number_format(500 * $myrRate, 2),
                                        "1000 {$symbol} = RM " . number_format(1000 * $myrRate, 2),
                                    ];

                                    if ($usdRate > 0) {
                                        $previews[] = "100 {$symbol} = $" . number_format(100 * $usdRate, 2);
                                    }

                                    return new \Illuminate\Support\HtmlString('
                                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                            <div class="text-green-700 font-medium mb-2">Conversion Examples:</div>
                                            <div class="space-y-1 text-sm">
                                                ' . implode('<br>', array_map(fn ($p) => '<div>' . $p . '</div>', $previews)) . '
                                            </div>
                                        </div>
                                    ');
                                })
                                ->live(),
                        ]),
                    ]),

                // Add a file upload for the flag (store in a separate table or use a JSON field)
                Section::make('Additional Information')
                    ->collapsible()
                    ->schema([
                        Forms\Components\FileUpload::make('flag')
                        ->label('Country Flag')
                        ->image()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('16:9')
                        ->directory('country-flags')
                        ->visibility('public')
                        ->deleteUploadedFileUsing(function ($file) {
                            // This ensures the old file is deleted when replaced
                            if ($file && file_exists(storage_path('app/public/' . $file))) {
                                unlink(storage_path('app/public/' . $file));
                            }
                        })
                        ->maxSize(1024) // 1MB max size
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                        ->downloadable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\ImageColumn::make('flag')
                ->defaultImageUrl(function (Country $record) {
                    if (!$record->code) {
                        return null;
                    }
                    // Try to use the external flag API if we have a country code
                    return 'https://flagcdn.com/w80/' . strtolower($record->code) . '.png';
                })
                ->circular()
                ->visibility(fn ($record): bool => $record->flag || $record->code),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('ISO Code')
                    ->formatStateUsing(fn ($state) => Str::upper($state))
                    ->searchable(),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->getStateUsing(fn (Country $record): bool => $record->status === 'active'),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->searchable()
                    ->formatStateUsing(fn ($state, Country $record) =>
                        $state . ($record->currency_symbol ? ' (' . $record->currency_symbol . ')' : '')),

                // Add Exchange Rate Columns
                Tables\Columns\TextColumn::make('myr_exchange_rate')
                    ->label('MYR Rate')
                    ->numeric(4)
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 4))
                    ->tooltip('Rate to Malaysian Ringgit'),

                Tables\Columns\TextColumn::make('usd_exchange_rate')
                    ->label('USD Rate')
                    ->numeric(4)
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 4) : '-')
                    ->tooltip('Rate to US Dollar'),

                Tables\Columns\TextColumn::make('exchange_rate_updated_at')
                    ->label('Rate Updated')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->tooltip('Last exchange rate update')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('states_count')
                    ->counts('states')
                    ->label('States')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->attribute('status'),

                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\Filter::make('with_states')
                    ->label('Has States')
                    ->query(fn (Builder $query) => $query->has('states')),

                Tables\Filters\Filter::make('without_states')
                    ->label('No States')
                    ->query(fn (Builder $query) => $query->doesntHave('states')),

                // Add Exchange Rate Filters
                Tables\Filters\Filter::make('outdated_rates')
                    ->label('Outdated Exchange Rates')
                    ->query(fn (Builder $query) => $query->where(function ($q) {
                        $q->whereNull('exchange_rate_updated_at')
                          ->orWhere('exchange_rate_updated_at', '<', now()->subHours(24));
                    }))
                    ->indicator('Outdated Rates'),

                Tables\Filters\Filter::make('no_usd_rate')
                    ->label('Missing USD Rate')
                    ->query(fn (Builder $query) => $query->whereNull('usd_exchange_rate'))
                    ->indicator('No USD Rate'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Add Update Exchange Rate Action
                    Tables\Actions\Action::make('updateExchangeRate')
                        ->icon('heroicon-o-arrow-path')
                        ->label('Update Exchange Rate')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('myr_rate')
                                ->label('New MYR Rate')
                                ->numeric()
                                ->step(0.0001)
                                ->required(),
                            Forms\Components\TextInput::make('usd_rate')
                                ->label('New USD Rate')
                                ->numeric()
                                ->step(0.0001),
                        ])
                        ->action(function (Country $record, array $data) {
                            $record->update([
                                'myr_exchange_rate' => $data['myr_rate'],
                                'usd_exchange_rate' => $data['usd_rate'],
                                'exchange_rate_updated_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Exchange rate updated for ' . $record->name)
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('toggleStatus')
                        ->icon(fn (Country $record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->label(fn (Country $record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (Country $record) => $record->status === 'active' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (Country $record) {
                            $record->status = $record->status === 'active' ? 'inactive' : 'active';
                            $record->save();

                            Notification::make()
                                ->title($record->name . ' ' . ($record->status === 'active' ? 'activated' : 'deactivated'))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Add Bulk Update Exchange Rates
                    Tables\Actions\BulkAction::make('updateRates')
                        ->icon('heroicon-o-arrow-path')
                        ->label('Update Exchange Rates')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            $currencyService = new CurrencyConverterService();
                            $success = $currencyService->updateExchangeRates();

                            if ($success) {
                                Notification::make()
                                    ->title('Exchange rates updated successfully')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to update exchange rates')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['status' => 'active']);

                            Notification::make()
                                ->title("Activated {$count} countries")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['status' => 'inactive']);

                            Notification::make()
                                ->title("Deactivated {$count} countries")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Countries Added')
            ->emptyStateDescription('Create your first country to get started with location management.')
            ->emptyStateIcon('heroicon-o-globe-alt')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Country')
                    ->url(route('filament.admin.resources.countries.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->paginated([25, 50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCountries::route('/'),
            'create' => Pages\CreateCountry::route('/create'),
            'edit' => Pages\EditCountry::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'currency'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'ISO Code' => $record->code,
            'Status' => ucfirst($record->status),
            'MYR Rate' => $record->myr_exchange_rate ? number_format($record->myr_exchange_rate, 4) : 'Not set',
        ];
    }
}
