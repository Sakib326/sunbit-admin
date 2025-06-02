<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourPackageResource\Pages;
use App\Filament\Resources\TourPackageResource\RelationManagers;
use App\Models\TourPackage;
use App\Models\TourCategory;
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
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class TourPackageResource extends Resource
{
    protected static ?string $model = TourPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Tour Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->description('Main tour package details')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (string $state, Forms\Set $set) =>
                                            $set('slug', Str::slug($state))),

                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique('tour_categories', 'slug'),

                                    Forms\Components\TextInput::make('meta_title')
                                        ->maxLength(255),

                                    Forms\Components\Textarea::make('meta_description')
                                        ->maxLength(160),

                                    Forms\Components\TagsInput::make('meta_keywords')
                                        ->separator(','),

                                    Forms\Components\Select::make('status')
                                        ->options([
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                        ])
                                        ->default('active')
                                        ->required(),
                                ])
                                ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                    return $action
                                        ->modalHeading('Create new category')
                                        ->modalWidth('lg')
                                        ->mutateFormDataUsing(function (array $data): array {
                                            if (empty($data['slug'])) {
                                                $data['slug'] = Str::slug($data['name']);
                                            }
                                            return $data;
                                        });
                                }),

                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $state, Forms\Set $set) =>
                                        $set('slug', Str::slug($state))),

                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])->columns(2),
                    ]),

                    Section::make('Tour Details')
                    ->description('Detailed tour information')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->label('Description')
                            ->placeholder('Describe your tour package...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                            ])
                            ->columnSpanFull(),

                        Grid::make()
                            ->schema([
                                Forms\Components\RichEditor::make('highlights')
                                    ->label('Tour Highlights')
                                    ->placeholder('List the main attractions and highlights...')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                    ]),

                                Forms\Components\RichEditor::make('tour_schedule')
                                    ->label('Tour Schedule')
                                    ->placeholder('Day-by-day itinerary...')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'bulletList',
                                        'orderedList',
                                        'h3',
                                        'link',
                                    ]),
                            ])->columns(2),
                    ]),

                    Section::make('Tour Locations')
                    ->description('Tour starting point and destination')
                    ->schema([
                        Forms\Components\Select::make('tour_type')
                            ->label('Tour Type')
                            ->options([
                                'domestic' => 'Domestic Tour',
                                'international' => 'International Tour',
                                'local' => 'Local Tour',
                            ])
                            ->default('domestic')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // FROM Location Section
                        Forms\Components\Fieldset::make('Starting Point (FROM)')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('from_country_id')
                                            ->label('Country')
                                            ->relationship('fromCountry', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Set $set) => $set('from_state_id', null)),

                                        Forms\Components\Select::make('from_state_id')
                                            ->label('State')
                                            ->options(
                                                fn (Forms\Get $get): array =>
                                                \App\Models\State::query()
                                                    ->where('country_id', $get('from_country_id'))
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set) {
                                                $set('from_zella_id', null);
                                                $set('from_upazilla_id', null);
                                            }),

                                        Forms\Components\Select::make('from_zella_id')
                                            ->label('Zella/District')
                                            ->options(
                                                fn (Forms\Get $get): array =>
                                                \App\Models\Zella::query()
                                                    ->where('state_id', $get('from_state_id'))
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Set $set) => $set('from_upazilla_id', null)),

                                        Forms\Components\Select::make('from_upazilla_id')
                                            ->label('Upazilla/Area')
                                            ->options(
                                                fn (Forms\Get $get): array =>
                                                \App\Models\Upazilla::query()
                                                    ->where('zella_id', $get('from_zella_id'))
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->searchable(),
                                    ]),

                                Forms\Components\Textarea::make('from_location_details')
                                    ->label('Specific Starting Location Details')
                                    ->rows(2)
                                    ->placeholder('e.g., Airport, Hotel, Specific landmark')
                                    ->columnSpanFull(),
                            ]),

                        // TO Location Section
                        Forms\Components\Fieldset::make('Destination (TO)')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('to_country_id')
                                            ->label('Country')
                                            ->relationship('toCountry', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Set $set) => $set('to_state_id', null)),

                                        Forms\Components\Select::make('to_state_id')
                                            ->label('State')
                                            ->options(
                                                fn (Forms\Get $get): array =>
                                                \App\Models\State::query()
                                                    ->where('country_id', $get('to_country_id'))
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set) {
                                                $set('to_zella_id', null);
                                                $set('to_upazilla_id', null);
                                            }),

                                        Forms\Components\Select::make('to_zella_id')
                                            ->label('Zella/District')
                                            ->options(
                                                fn (Forms\Get $get): array =>
                                                \App\Models\Zella::query()
                                                    ->where('state_id', $get('to_state_id'))
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Set $set) => $set('to_upazilla_id', null)),

                                        Forms\Components\Select::make('to_upazilla_id')
                                            ->label('Upazilla/Area')
                                            ->options(
                                                fn (Forms\Get $get): array =>
                                                \App\Models\Upazilla::query()
                                                    ->where('zella_id', $get('to_zella_id'))
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->searchable(),
                                    ]),

                                Forms\Components\Textarea::make('to_location_details')
                                    ->label('Specific Destination Details')
                                    ->rows(2)
                                    ->placeholder('e.g., Hotel, Resort, Specific attractions')
                                    ->columnSpanFull(),
                            ]),
                    ]),


                    Section::make('Duration & Pricing')
                    ->description('Tour duration and pricing information')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('number_of_days')
                                    ->label('Days')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state && $state > 1) {
                                            $set('number_of_nights', $state - 1);
                                        }
                                    }),

                                Forms\Components\TextInput::make('number_of_nights')
                                    ->label('Nights')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),

                                Forms\Components\TextInput::make('base_price_adult')
                                    ->label('Adult Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0.00),

                                Forms\Components\TextInput::make('base_price_child')
                                    ->label('Child Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0.00),

                                Forms\Components\TextInput::make('max_booking_per_day')
                                    ->label('Max Booking Per Day')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable()
                                    ->helperText('Default maximum bookings allowed per day. Special dates can override this.'),

                                Forms\Components\TextInput::make('agent_commission_percent')
                                    ->label('Agent Commission')
                                    ->numeric()
                                    ->suffix('%')
                                    ->nullable()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('Optional commission percentage'),
                            ])->columns(3),
                    ]),

                    Section::make('Inclusions & Exclusions')
                    ->description('What is included and excluded in the tour')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\RichEditor::make('whats_included')
                                    ->label('What\'s Included')
                                    ->placeholder('List what is included in the tour...')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                        'h1',
                                        'h2',
                                        'h3',
                                        'h4',
                                        'h5',
                                        'h6',
                                        'strike',
                                        'blockquote',
                                        'codeBlock',
                                        'redo',
                                        'undo',
                                        'hr',
                                        'alignLeft',
                                        'alignCenter',
                                        'alignRight',
                                        'alignJustify',
                                        'color',
                                        'backgroundColor'
                                    ]),

                                Forms\Components\RichEditor::make('whats_excluded')
                                    ->label('What\'s Excluded')
                                    ->placeholder('List what is not included...')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'bulletList',
                                        'orderedList',
                                    ]),
                            ])->columns(2),
                    ]),


                    Section::make('Resources & Features')
                    ->description('Additional resources and feature flags')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('area_map_url')
                                    ->label('Map URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://goo.gl/maps/...')
                                    ->helperText('Google Maps or similar URL'),

                                    Forms\Components\FileUpload::make('guide_pdf_url')
                                    ->label('Tour Guide PDF')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->directory('tour-guides')
                                    ->visibility('public')
                                    ->maxSize(5120) // 5MB
                                    ->helperText('Upload a PDF guide for this tour')
                                    ->deleteUploadedFileUsing(function ($file) {
                                        if ($file && file_exists(storage_path('app/public/' . $file))) {
                                            unlink(storage_path('app/public/' . $file));
                                        }
                                    }),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured Tour')
                                    ->helperText('Display prominently on the website'),

                                Forms\Components\Toggle::make('is_popular')
                                    ->label('Popular Tour')
                                    ->helperText('Mark as a popular destination'),
                            ])->columns(2),
                    ]),

                Section::make('SEO Information')
                    ->description('Search engine optimization settings')
                    ->collapsible()
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(255)
                                    ->placeholder('Leave empty to use tour title')
                                    ->helperText('Recommended: 50-60 characters'),

                                Forms\Components\Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(2)
                                    ->maxLength(160)
                                    ->placeholder('Brief description for search engines')
                                    ->helperText('Recommended: 150-160 characters'),

                                Forms\Components\TagsInput::make('meta_keywords')
                                    ->label('Meta Keywords')
                                    ->separator(',')
                                    ->placeholder('E.g. adventure, family, tours')
                                    ->helperText('Separate keywords with commas')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(
                        fn (TourPackage $record): string =>
                        $record->number_of_days . 'D' .
                        ($record->number_of_nights > 0 ? '/' . $record->number_of_nights . 'N' : '')
                    )
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('base_price_adult')
                    ->label('Adult Price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('base_price_child')
                    ->label('Child Price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->label('Featured'),

                Tables\Columns\IconColumn::make('is_popular')
                    ->boolean()
                    ->trueIcon('heroicon-o-fire')
                    ->falseIcon('heroicon-o-fire')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->label('Popular'),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (TourPackage $record): bool => $record->status === 'active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->placeholder('All tours')
                    ->trueLabel('Featured tours')
                    ->falseLabel('Not featured'),

                Tables\Filters\TernaryFilter::make('is_popular')
                    ->label('Popular')
                    ->placeholder('All tours')
                    ->trueLabel('Popular tours')
                    ->falseLabel('Not popular'),

                    Tables\Filters\SelectFilter::make('tour_type')
                    ->options([
                        'domestic' => 'Domestic',
                        'international' => 'International',
                        'local' => 'Local',
                    ]),

                Tables\Filters\SelectFilter::make('from_country')
                    ->relationship('fromCountry', 'name')
                    ->searchable()
                    ->preload()
                    ->label('From Country'),

                Tables\Filters\SelectFilter::make('to_country')
                    ->relationship('toCountry', 'name')
                    ->searchable()
                    ->preload()
                    ->label('To Country'),

                Tables\Filters\SelectFilter::make('from_state')
                    ->relationship('fromState', 'name')
                    ->searchable()
                    ->preload()
                    ->label('From State'),

                Tables\Filters\SelectFilter::make('to_state')
                    ->relationship('toState', 'name')
                    ->searchable()
                    ->preload()
                    ->label('To State'),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price_from')
                                    ->label('Min Price (Adult)')
                                    ->numeric(),
                                Forms\Components\TextInput::make('price_to')
                                    ->label('Max Price (Adult)')
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('base_price_adult', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('base_price_adult', '<=', $price),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggleStatus')
                        ->icon(fn (TourPackage $record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->label(fn (TourPackage $record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (TourPackage $record) => $record->status === 'active' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (TourPackage $record) {
                            $record->status = $record->status === 'active' ? 'inactive' : 'active';
                            $record->save();

                            Notification::make()
                                ->title($record->title . ' ' . ($record->status === 'active' ? 'activated' : 'deactivated'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('toggleFeatured')
                        ->icon(fn (TourPackage $record) => $record->is_featured ? 'heroicon-o-star' : 'heroicon-s-star')
                        ->label(fn (TourPackage $record) => $record->is_featured ? 'Unfeature' : 'Feature')
                        ->color(fn (TourPackage $record) => $record->is_featured ? 'gray' : 'warning')
                        ->action(function (TourPackage $record) {
                            $record->is_featured = !$record->is_featured;
                            $record->save();

                            Notification::make()
                                ->title($record->title . ' ' . ($record->is_featured ? 'featured' : 'unfeatured'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['status' => 'active']);

                            Notification::make()
                                ->title("{$count} tour packages activated")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['status' => 'inactive']);

                            Notification::make()
                                ->title("{$count} tour packages deactivated")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Mark as Featured')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['is_featured' => true]);

                            Notification::make()
                                ->title("{$count} tour packages marked as featured")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Tour Packages')
            ->emptyStateDescription('Create your first tour package to get started')
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Tour Package')
                    ->url(route('filament.admin.resources.tour-packages.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItinerariesRelationManager::class,
            RelationManagers\GalleriesRelationManager::class,
            RelationManagers\FaqsRelationManager::class,
            RelationManagers\BookingLimitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTourPackages::route('/'),
            'create' => Pages\CreateTourPackage::route('/create'),
            'edit' => Pages\EditTourPackage::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'title', 'slug', 'description', 'category.name',
            'fromCountry.name', 'fromState.name', 'fromZella.name', 'fromUpazilla.name',
            'toCountry.name', 'toState.name', 'toZella.name', 'toUpazilla.name'
        ];
    }

    // Update the getGlobalSearchResultDetails method:

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Category' => $record->category->name,
            'Duration' => $record->number_of_days . 'D/' . $record->number_of_nights . 'N',
            'Price' => '$' . number_format($record->base_price_adult, 2),
            'Route' => $record->getTourRoute(),
            'Type' => $record->getTourTypeLabel(),
        ];
    }
}
