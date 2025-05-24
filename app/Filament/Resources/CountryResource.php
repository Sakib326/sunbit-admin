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
                                    ->placeholder('e.g. US, GBR'),

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
                                    ->maxLength(255)
                                    ->placeholder('e.g. US Dollar, Euro'),

                                Forms\Components\TextInput::make('currency_symbol')
                                    ->maxLength(10)
                                    ->placeholder('e.g. $, €, £'),
                            ])->columns(2),
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
                    ->searchable()
                    ->formatStateUsing(fn ($state, Country $record) =>
                        $state . ($record->currency_symbol ? ' (' . $record->currency_symbol . ')' : '')),
                Tables\Columns\TextColumn::make('states_count')
                    ->counts('states')
                    ->label('States')
                    ->sortable(),
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
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
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
        ];
    }
}
