<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZellaResource\Pages;
use App\Filament\Resources\ZellaResource\RelationManagers;
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

class ZellaResource extends Resource
{
    protected static ?string $model = Zella::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Location Management';

    protected static ?int $navigationSort = 3;

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
                    ->description('Specify where this zella belongs in the location hierarchy')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\Select::make('country_id')
                                    ->label('Country')
                                    ->options(Country::where('status', 'active')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('state_id', null)),

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
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('code')
                                            ->maxLength(10),
                                        Forms\Components\Hidden::make('country_id')
                                            ->default(function (Forms\Get $get) {
                                                return $get('../../country_id');
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
                                            ->modalHeading('Create new state')
                                            ->modalWidth('md');
                                    }),
                            ])->columns(2),
                    ]),

                Section::make('Zella Information')
                    ->description('Basic details about the zella')
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
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->maxLength(255)
                                    ->placeholder('Optional code identifier')
                                    ->helperText('A short code for the zella (optional)'),

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
            ->defaultSort('state_id')
            ->columns([
                Tables\Columns\TextColumn::make('state.country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('state.name')
                    ->label('State')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

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
                    ->getStateUsing(fn (Zella $record): bool => $record->status === 'active'),

                Tables\Columns\TextColumn::make('upazillas_count')
                    ->counts('upazillas')
                    ->label('Upazillas')
                    ->sortable(),

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
                Tables\Filters\SelectFilter::make('state')
                    ->relationship('state', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('country')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('state', function ($q) use ($data) {
                            $q->where('country_id', $data['value']);
                        });
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->attribute('status'),

                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\Filter::make('with_upazillas')
                    ->label('Has Upazillas')
                    ->query(fn (Builder $query): Builder => $query->has('upazillas')),

                Tables\Filters\Filter::make('without_upazillas')
                    ->label('No Upazillas')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('upazillas')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggleStatus')
                        ->icon(fn (Zella $record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->label(fn (Zella $record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (Zella $record) => $record->status === 'active' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (Zella $record) {
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
                                ->title("Activated {$count} zellas")
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
                                ->title("Deactivated {$count} zellas")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Zellas Found')
            ->emptyStateDescription('Create your first zella to continue building your location hierarchy.')
            ->emptyStateIcon('heroicon-o-map-pin')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Zella')
                    ->url(route('filament.admin.resources.zellas.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->paginated([25, 50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UpazillasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZellas::route('/'),
            'create' => Pages\CreateZella::route('/create'),
            'view' => Pages\ViewZella::route('/{record}'),
            'edit' => Pages\EditZella::route('/{record}/edit'),
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
        return ['name', 'code', 'state.name', 'state.country.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'State' => $record->state->name,
            'Country' => $record->state->country->name,
        ];
    }
}
