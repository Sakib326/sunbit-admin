<?php

namespace App\Filament\Resources\TourCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $inverseRelationship = 'category';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, Forms\Set $set) =>
                                $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, column: 'slug'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ]),

                Section::make('Pricing')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('base_price_adult')
                                    ->label('Adult Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),

                                Forms\Components\TextInput::make('base_price_child')
                                    ->label('Child Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),

                                Forms\Components\TextInput::make('agent_commission_percent')
                                    ->label('Agent Commission')
                                    ->required()
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(10)
                                    ->minValue(0)
                                    ->maxValue(100),
                            ])->columns(3),
                    ]),

                Section::make('Tour Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),

                        Forms\Components\Textarea::make('highlights')
                            ->label('Highlights')
                            ->placeholder('Enter each highlight on a new line')
                            ->helperText('List the main attractions and highlights of the tour')
                            ->rows(3),

                        Forms\Components\Textarea::make('whats_included')
                            ->label('What\'s Included')
                            ->rows(3),

                        Forms\Components\Textarea::make('whats_excluded')
                            ->label('What\'s Excluded')
                            ->rows(3),
                    ]),

                Section::make('Schedule & Map')
                    ->schema([
                        Forms\Components\Textarea::make('tour_schedule')
                            ->label('Tour Schedule')
                            ->helperText('Day-by-day itinerary')
                            ->rows(4),

                        Forms\Components\TextInput::make('area_map_url')
                            ->label('Map URL')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://goo.gl/maps/...')
                            ->helperText('Google Maps or similar URL'),
                    ]),

                Section::make('SEO Information')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('meta_description')
                            ->rows(2)
                            ->maxLength(160),

                        Forms\Components\TagsInput::make('meta_keywords')
                            ->separator(','),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),

                Tables\Columns\TextColumn::make('base_price_adult')
                    ->money('USD')
                    ->sortable()
                    ->label('Adult Price'),

                Tables\Columns\TextColumn::make('base_price_child')
                    ->money('USD')
                    ->sortable()
                    ->label('Child Price'),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record): bool => $record->status === 'active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleStatus')
                    ->icon(fn ($record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->label(fn ($record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->color(fn ($record) => $record->status === 'active' ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->status = $record->status === 'active' ? 'inactive' : 'active';
                        $record->save();

                        Notification::make()
                            ->title($record->title . ' ' . ($record->status === 'active' ? 'activated' : 'deactivated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('changeStatus')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each->update(['status' => $data['status']]);

                            Notification::make()
                                ->title('Status updated')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No Tour Packages')
            ->emptyStateDescription('Create tour packages for this category')
            ->emptyStateIcon('heroicon-o-ticket');
    }
}
