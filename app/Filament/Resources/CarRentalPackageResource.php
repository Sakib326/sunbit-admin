<?php

// filepath: app/Filament/Resources/CarRentalPackageResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\CarRentalPackageResource\Pages;
use App\Models\CarRentalPackage;
use App\Models\Country;
use App\Models\State;
use App\Models\Zella;
use App\Models\Upazilla;
use App\Enums\CarRentalEnums;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CarRentalPackageResource extends Resource
{
    protected static ?string $model = CarRentalPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Car Rentals';
    protected static ?string $navigationGroup = 'Travel Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // Step 1: Basic Car Information
                    Forms\Components\Wizard\Step::make('Car Information')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Package Title')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                            if ($operation !== 'create') {
                                                return;
                                            }
                                            $set('slug', Str::slug($state));
                                        }),

                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(CarRentalPackage::class, 'slug', ignoreRecord: true)
                                        ->alphaDash(),
                                ]),

                            Forms\Components\Textarea::make('description')
                                ->label('Package Description')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('car_brand')
                                        ->label('Car Brand')
                                        ->required()
                                        ->placeholder('e.g., Toyota, Honda, Nissan'),

                                    Forms\Components\TextInput::make('car_model')
                                        ->label('Car Model')
                                        ->required()
                                        ->placeholder('e.g., Vios, City, Almera'),
                                ]),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\Select::make('pax_capacity')
                                        ->label('Passenger Capacity')
                                        ->options(CarRentalEnums::PAX_CAPACITY)
                                        ->required(),

                                    Forms\Components\Select::make('transmission')
                                        ->label('Transmission Type')
                                        ->options(CarRentalEnums::TRANSMISSION_TYPES)
                                        ->required(),

                                    Forms\Components\Select::make('air_condition')
                                        ->label('Air Conditioning')
                                        ->options(CarRentalEnums::AIR_CONDITION_TYPES)
                                        ->required(),
                                ]),

                            Forms\Components\Select::make('chauffeur_option')
                                ->label('Chauffeur Option')
                                ->options(CarRentalEnums::CHAUFFEUR_OPTIONS)
                                ->required()
                                ->helperText('Choose whether this car includes a chauffeur or is self-drive'),
                        ]),

                    // Step 2: Service Locations
                    Forms\Components\Wizard\Step::make('Service Locations')
                        ->schema([
                            Forms\Components\Section::make('Available Service Areas')
                                ->description('Select all locations where this car rental service is available')
                                ->schema([
                                    Forms\Components\Repeater::make('locations')
                                        ->relationship()
                                        ->schema([
                                            Forms\Components\Grid::make(4)
                                                ->schema([
                                                    Forms\Components\Select::make('country_id')
                                                        ->label('Country')
                                                        ->relationship('country', 'name')
                                                        ->searchable()
                                                        ->preload()
                                                        ->live()
                                                        ->afterStateUpdated(fn (Forms\Set $set) => $set('state_id', null)),

                                                    Forms\Components\Select::make('state_id')
                                                        ->label('State')
                                                        ->options(
                                                            fn (Forms\Get $get): array =>
                                                            State::query()
                                                                ->where('country_id', $get('country_id'))
                                                                ->pluck('name', 'id')
                                                                ->toArray()
                                                        )
                                                        ->searchable()
                                                        ->live()
                                                        ->afterStateUpdated(function (Forms\Set $set) {
                                                            $set('zella_id', null);
                                                            $set('upazilla_id', null);
                                                        }),

                                                    Forms\Components\Select::make('zella_id')
                                                        ->label('Zella/District')
                                                        ->options(
                                                            fn (Forms\Get $get): array =>
                                                            Zella::query()
                                                                ->where('state_id', $get('state_id'))
                                                                ->pluck('name', 'id')
                                                                ->toArray()
                                                        )
                                                        ->searchable()
                                                        ->live()
                                                        ->afterStateUpdated(fn (Forms\Set $set) => $set('upazilla_id', null)),

                                                    Forms\Components\Select::make('upazilla_id')
                                                        ->label('Upazilla/Area')
                                                        ->options(
                                                            fn (Forms\Get $get): array =>
                                                            Upazilla::query()
                                                                ->where('zella_id', $get('zella_id'))
                                                                ->pluck('name', 'id')
                                                                ->toArray()
                                                        )
                                                        ->searchable(),
                                                ]),
                                        ])
                                        ->defaultItems(1)
                                        ->addActionLabel('Add Another Location')
                                        ->collapsible()
                                        ->itemLabel(
                                            fn (array $state): ?string =>
                                            $state['upazilla_id'] ? Upazilla::find($state['upazilla_id'])?->name : 'New Location'
                                        ),
                                ]),
                        ]),

                    // Step 3: Pricing & Availability
                    Forms\Components\Wizard\Step::make('Pricing & Availability')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('daily_price')
                                        ->label('Daily Rental Price')
                                        ->required()
                                        ->numeric()
                                        ->prefix('৳')
                                        ->minValue(0),

                                    Forms\Components\TextInput::make('agent_commission_percent')
                                        ->label('Agent Commission (%)')
                                        ->numeric()
                                        ->suffix('%')
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->step(0.1)
                                        ->helperText('Commission percentage for agents'),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('total_cars')
                                        ->label('Total Cars Available')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->helperText('Total number of cars in this package'),

                                    Forms\Components\Select::make('status')
                                        ->label('Package Status')
                                        ->options([
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                        ])
                                        ->default('active')
                                        ->required(),
                                ]),
                        ]),

                    // Step 4: Media & SEO
                    Forms\Components\Wizard\Step::make('Media & SEO')
                        ->schema([
                            Forms\Components\Section::make('Featured Image')
                                ->schema([
                                    Forms\Components\FileUpload::make('featured_image')
                                        ->label('Featured Image')
                                        ->image()
                                        ->directory('car-rentals')
                                        ->visibility('public')
                                        ->imageEditor()
                                        ->helperText('Upload a high-quality image of the car'),
                                ]),

                            Forms\Components\Section::make('SEO Settings')
                                ->schema([
                                    Forms\Components\TextInput::make('meta_title')
                                        ->label('Meta Title')
                                        ->maxLength(60)
                                        ->helperText('SEO title for search engines'),

                                    Forms\Components\Textarea::make('meta_description')
                                        ->label('Meta Description')
                                        ->maxLength(160)
                                        ->rows(3)
                                        ->helperText('SEO description for search engines'),
                                ])
                                ->collapsed(),
                        ]),

                    // Step 5: Special Date Overrides - FIXED VERSION
                    Forms\Components\Wizard\Step::make('Special Date Settings')
                        ->schema([
                            Forms\Components\Section::make('Booking Limits & Special Pricing')
                                ->description('Set special pricing or availability limits for specific dates')
                                ->schema([
                                    Forms\Components\Repeater::make('bookingLimits')
                                        ->relationship()
                                        ->schema([
                                            Forms\Components\Grid::make(3)
                                                ->schema([
                                                    Forms\Components\DatePicker::make('date')
                                                        ->label('Date')
                                                        ->required()
                                                        ->minDate(today())
                                                        ->displayFormat('d/m/Y')
                                                        ->native(false),

                                                    Forms\Components\TextInput::make('max_booking_override')
                                                        ->label('Max Bookings Override')
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->placeholder('Leave empty to use default')
                                                        ->helperText('Override total cars for this date'),

                                                    Forms\Components\TextInput::make('special_daily_price')
                                                        ->label('Special Price')
                                                        ->numeric()
                                                        ->prefix('৳')
                                                        ->minValue(0)
                                                        ->placeholder('Leave empty to use default')
                                                        ->helperText('Special price for this date'),
                                                ]),
                                        ])
                                        ->addActionLabel('Add Special Date')
                                        ->collapsible()
                                        ->reorderable(false)
                                        ->itemLabel(
                                            fn (array $state): ?string =>
                                            $state['date'] ? \Carbon\Carbon::parse($state['date'])->format('d M Y') : 'New Date'
                                        )
                                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                            // Clean up empty values before saving
                                            if (empty($data['max_booking_override'])) {
                                                $data['max_booking_override'] = null;
                                            }
                                            if (empty($data['special_daily_price'])) {
                                                $data['special_daily_price'] = null;
                                            }
                                            return $data;
                                        })
                                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                            // Clean up empty values before saving
                                            if (empty($data['max_booking_override'])) {
                                                $data['max_booking_override'] = null;
                                            }
                                            if (empty($data['special_daily_price'])) {
                                                $data['special_daily_price'] = null;
                                            }
                                            return $data;
                                        }),
                                ])
                                ->collapsed(),
                        ]),
                ])
                ->columnSpanFull()
                ->skippable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Package Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('car_brand')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('car_model')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('pax_capacity')
                    ->label('Capacity')
                    ->formatStateUsing(fn ($state) => CarRentalEnums::PAX_CAPACITY[$state] ?? $state),

                Tables\Columns\BadgeColumn::make('transmission')
                    ->label('Transmission')
                    ->colors([
                        'success' => 'automatic',
                        'warning' => 'manual',
                    ]),

                Tables\Columns\BadgeColumn::make('chauffeur_option')
                    ->label('Chauffeur')
                    ->colors([
                        'info' => 'with_chauffeur',
                        'secondary' => 'without_chauffeur',
                    ])
                    ->formatStateUsing(fn ($state) => CarRentalEnums::CHAUFFEUR_OPTIONS[$state] ?? $state),

                Tables\Columns\TextColumn::make('daily_price')
                    ->label('Daily Price')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_cars')
                    ->label('Cars')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('agent_commission_percent')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state) => $state ? $state . '%' : 'N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('locations_count')
                    ->label('Locations')
                    ->counts('locations')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('booking_limits_count')
                    ->label('Special Dates')
                    ->counts('bookingLimits')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('chauffeur_option')
                    ->label('Chauffeur Option')
                    ->options(CarRentalEnums::CHAUFFEUR_OPTIONS),

                Tables\Filters\SelectFilter::make('transmission')
                    ->options(CarRentalEnums::TRANSMISSION_TYPES),

                Tables\Filters\SelectFilter::make('pax_capacity')
                    ->label('Passenger Capacity')
                    ->options(CarRentalEnums::PAX_CAPACITY),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_price')
                                    ->numeric()
                                    ->prefix('৳')
                                    ->placeholder('Min Price'),
                                Forms\Components\TextInput::make('max_price')
                                    ->numeric()
                                    ->prefix('৳')
                                    ->placeholder('Max Price'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_price'],
                                fn (Builder $query, $price): Builder => $query->where('daily_price', '>=', $price),
                            )
                            ->when(
                                $data['max_price'],
                                fn (Builder $query, $price): Builder => $query->where('daily_price', '<=', $price),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('clone')
                    ->label('Clone Package')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record) {
                        $newPackage = $record->replicate();
                        $newPackage->title = $record->title . ' (Copy)';
                        $newPackage->slug = Str::slug($newPackage->title);
                        $newPackage->save();

                        // Clone locations
                        foreach ($record->locations as $location) {
                            $newPackage->locations()->create($location->toArray());
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Package Cloned')
                            ->body('Car rental package has been successfully cloned.')
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn ($record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->icon('heroicon-o-power')
                    ->color(fn ($record) => $record->status === 'active' ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active'
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Status Updated')
                            ->body('Package status has been updated.')
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['status' => 'active']);
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each->update(['status' => 'inactive']);
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Car Information')
                    ->schema([
                        Components\ImageEntry::make('featured_image')
                            ->hiddenLabel()
                            ->height(200),
                        Components\TextEntry::make('title')
                            ->size('lg')
                            ->weight('bold'),
                        Components\TextEntry::make('description')
                            ->columnSpanFull(),
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('car_brand')
                                    ->label('Brand'),
                                Components\TextEntry::make('car_model')
                                    ->label('Model'),
                                Components\TextEntry::make('pax_capacity')
                                    ->label('Capacity')
                                    ->formatStateUsing(fn ($state) => CarRentalEnums::PAX_CAPACITY[$state] ?? $state)
                                    ->badge(),
                            ]),
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('transmission')
                                    ->formatStateUsing(fn ($state) => CarRentalEnums::TRANSMISSION_TYPES[$state] ?? $state)
                                    ->badge(),
                                Components\TextEntry::make('air_condition')
                                    ->label('Air Conditioning')
                                    ->formatStateUsing(fn ($state) => CarRentalEnums::AIR_CONDITION_TYPES[$state] ?? $state)
                                    ->badge(),
                                Components\TextEntry::make('chauffeur_option')
                                    ->label('Chauffeur')
                                    ->formatStateUsing(fn ($state) => CarRentalEnums::CHAUFFEUR_OPTIONS[$state] ?? $state)
                                    ->badge(),
                            ]),
                    ])
                    ->columns(2),

                Components\Section::make('Pricing & Availability')
                    ->schema([
                        Components\TextEntry::make('daily_price')
                            ->label('Daily Price')
                            ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2))
                            ->size('lg')
                            ->weight('bold'),
                        Components\TextEntry::make('agent_commission_percent')
                            ->label('Agent Commission')
                            ->formatStateUsing(fn ($state) => $state ? $state . '%' : 'N/A'),
                        Components\TextEntry::make('total_cars')
                            ->label('Total Cars'),
                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                    ])
                    ->columns(4),

                Components\Section::make('Service Locations')
                    ->schema([
                        Components\RepeatableEntry::make('locations')
                            ->schema([
                                Components\TextEntry::make('country.name')
                                    ->label('Country'),
                                Components\TextEntry::make('state.name')
                                    ->label('State'),
                                Components\TextEntry::make('zella.name')
                                    ->label('Zella'),
                                Components\TextEntry::make('upazilla.name')
                                    ->label('Upazilla'),
                            ])
                            ->columns(4),
                    ]),

                Components\Section::make('Special Date Settings')
                    ->schema([
                        Components\RepeatableEntry::make('bookingLimits')
                            ->schema([
                                Components\TextEntry::make('date')
                                    ->date('d M Y'),
                                Components\TextEntry::make('max_booking_override')
                                    ->label('Max Bookings')
                                    ->placeholder('Default'),
                                Components\TextEntry::make('special_daily_price')
                                    ->label('Special Price')
                                    ->formatStateUsing(fn ($state) => $state ? '৳' . number_format($state, 2) : 'Default Price'),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn ($record) => $record->bookingLimits->count() > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarRentalPackages::route('/'),
            'create' => Pages\CreateCarRentalPackage::route('/create'),
            'view' => Pages\ViewCarRentalPackage::route('/{record}'),
            'edit' => Pages\EditCarRentalPackage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Brand' => $record->car_brand,
            'Model' => $record->car_model,
            'Price' => '৳' . number_format($record->daily_price, 2) . '/day',
        ];
    }
}
