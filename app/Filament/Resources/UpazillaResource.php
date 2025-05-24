<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UpazillaResource\Pages;
use App\Filament\Resources\UpazillaResource\RelationManagers;
use App\Models\Upazilla;
use App\Models\Zella;
use App\Models\State;
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

class UpazillaResource extends Resource
{
    protected static ?string $model = Upazilla::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Location Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Location Hierarchy')
                    ->description('Select this upazilla\'s position in the location hierarchy')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\Select::make('country_id')
                                    ->label('Country')
                                    ->options(Country::where('status', 'active')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('state_id', null))
                                    ->required(),

                                Forms\Components\Select::make('state_id')
                                    ->label('State')
                                    ->options(function (Forms\Get $get) {
                                        $countryId = $get('country_id');
                                        if (!$countryId) {
                                            return [];
                                        }

                                        return State::where('country_id', $countryId)
                                            ->where('status', 'active')
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('zella_id', null))
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => filled($get('country_id'))),

                                Forms\Components\Select::make('zella_id')
                                    ->label('Zella')
                                    ->options(function (Forms\Get $get) {
                                        $stateId = $get('state_id');
                                        if (!$stateId) {
                                            return [];
                                        }

                                        return Zella::where('state_id', $stateId)
                                            ->where('status', 'active')
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('code')
                                            ->maxLength(255),
                                        Forms\Components\Hidden::make('state_id')
                                            ->default(function (Forms\Get $get) {
                                                return $get('../../state_id');
                                            }),
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
                                            ->modalHeading('Create new zella')
                                            ->modalWidth('md');
                                    })
                                    ->visible(fn (Forms\Get $get) => filled($get('state_id'))),
                            ])->columns(3),
                    ]),

                Section::make('Upazilla Details')
                    ->description('Basic information about the upazilla')
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
                                    ->maxLength(255)
                                    ->placeholder('e.g. UPZ-001')
                                    ->helperText('Area or administrative code (optional)'),

                                Forms\Components\TextInput::make('postal_code')
                                    ->maxLength(255)
                                    ->placeholder('e.g. 10001')
                                    ->helperText('ZIP or postal code for this area'),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('zella_id')
            ->columns([
                Tables\Columns\TextColumn::make('zella.state.country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('zella.state.name')
                    ->label('State')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('zella.name')
                    ->label('Zella')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('postal_code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : '—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->getStateUsing(fn (Upazilla $record): bool => $record->status === 'active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zella')
                    ->relationship('zella', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('state')
                    ->label('State')
                    ->options(State::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('zella', function ($q) use ($data) {
                            $q->where('state_id', $data['value']);
                        });
                    }),

                Tables\Filters\SelectFilter::make('country')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('zella.state', function ($q) use ($data) {
                            $q->where('country_id', $data['value']);
                        });
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\Filter::make('has_postal_code')
                    ->label('Has Postal Code')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('postal_code')),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggleStatus')
                        ->icon(fn (Upazilla $record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->label(fn (Upazilla $record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (Upazilla $record) => $record->status === 'active' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (Upazilla $record) {
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
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['status' => 'active']);

                            Notification::make()
                                ->title("Activated {$count} upazillas")
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
                                ->title("Deactivated {$count} upazillas")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Upazillas Found')
            ->emptyStateDescription('Create your first upazilla to complete your location hierarchy.')
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Upazilla')
                    ->url(route('filament.admin.resources.upazillas.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->paginated([25, 50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [
            // For future expansion if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUpazillas::route('/'),
            'create' => Pages\CreateUpazilla::route('/create'),
            'view' => Pages\ViewUpazilla::route('/{record}'),
            'edit' => Pages\EditUpazilla::route('/{record}/edit'),
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
        return ['name', 'code', 'postal_code', 'zella.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Zella' => $record->zella->name,
            'Postal Code' => $record->postal_code ?? 'None',
            'Status' => ucfirst($record->status),
        ];
    }
}
