<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StateResource\Pages;
use App\Filament\Resources\StateResource\RelationManagers;
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

class StateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Location Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->description('State identification and relationship')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\Select::make('country_id')
                                    ->label('Country')
                                    ->relationship('country', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('code')
                                            ->label('Country Code')
                                            ->maxLength(3),
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
                                            ->modalHeading('Create new country')
                                            ->modalWidth('lg');
                                    }),

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
                                    ->maxLength(10)
                                    ->helperText('Optional short code for the state')
                                    ->placeholder('e.g. CA, NY'),
                            ])->columns(2),
                    ]),

                // ✅ ADD NEW DESCRIPTION SECTION
                Section::make('Description & Details')
                    ->description('Detailed information about the state')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->label('State Description')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'link',
                                'blockquote',
                            ])
                            ->placeholder('Write a detailed description about this state, its attractions, culture, history, etc.')
                            ->helperText('This description will be used on the frontend to showcase the state')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_top_destination')
                            ->label('Top Destination')
                            ->helperText('Mark this state as a top travel destination')
                            ->inline(false)
                            ->onIcon('heroicon-m-star')
                            ->offIcon('heroicon-m-star')
                            ->onColor('warning')
                            ->columnSpanFull(),
                    ]),

                Section::make('State Settings')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),

                                Forms\Components\FileUpload::make('image')
                                    ->label('State Image')
                                    ->image()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('16:9')
                                    ->directory('state-images')
                                    ->visibility('public')
                                    ->deleteUploadedFileUsing(function ($file) {
                                        if ($file && file_exists(storage_path('app/public/' . $file))) {
                                            unlink(storage_path('app/public/' . $file));
                                        }
                                    })
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->helperText('Upload an image to represent this state. Max 1MB.'),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('country_id', 'asc')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->circular()
                    ->visibility(fn ($record): bool => !empty($record->image)),

                Tables\Columns\TextColumn::make('country.name')
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
                    ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : '—'),

                // ✅ ADD TOP DESTINATION COLUMN
                Tables\Columns\IconColumn::make('is_top_destination')
                    ->label('Top Dest.')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable()
                    ->tooltip('Top Destination'),

                // ✅ ADD DESCRIPTION EXCERPT COLUMN
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->getDescriptionExcerpt(200);
                    })
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->getStateUsing(fn (State $record): bool => $record->status === 'active'),

                Tables\Columns\TextColumn::make('zellas_count')
                    ->counts('zellas')
                    ->label('Zellas')
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
                Tables\Filters\SelectFilter::make('country')
                    ->relationship('country', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->attribute('status'),

                // ✅ ADD TOP DESTINATION FILTER
                Tables\Filters\TernaryFilter::make('is_top_destination')
                    ->label('Top Destination')
                    ->placeholder('All states')
                    ->trueLabel('Top destinations only')
                    ->falseLabel('Regular states only'),

                // ✅ ADD DESCRIPTION FILTER
                Tables\Filters\Filter::make('has_description')
                    ->label('Has Description')
                    ->query(fn (Builder $query) => $query->whereNotNull('description')->where('description', '!=', '')),

                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\Filter::make('with_zellas')
                    ->label('Has Zellas')
                    ->query(fn (Builder $query) => $query->has('zellas')),

                Tables\Filters\Filter::make('without_zellas')
                    ->label('No Zellas')
                    ->query(fn (Builder $query) => $query->doesntHave('zellas')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // ✅ ADD TOGGLE TOP DESTINATION ACTION
                    Tables\Actions\Action::make('toggleTopDestination')
                        ->icon(fn (State $record) => $record->is_top_destination ? 'heroicon-s-star' : 'heroicon-o-star')
                        ->label(fn (State $record) => $record->is_top_destination ? 'Remove from Top' : 'Mark as Top')
                        ->color(fn (State $record) => $record->is_top_destination ? 'gray' : 'warning')
                        ->requiresConfirmation()
                        ->action(function (State $record) {
                            $record->is_top_destination = !$record->is_top_destination;
                            $record->save();

                            Notification::make()
                                ->title($record->name . ' ' . ($record->is_top_destination ? 'marked as top destination' : 'removed from top destinations'))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('toggleStatus')
                        ->icon(fn (State $record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->label(fn (State $record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (State $record) => $record->status === 'active' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (State $record) {
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
                    // ✅ ADD BULK TOP DESTINATION ACTIONS
                    Tables\Actions\BulkAction::make('markAsTopDestination')
                        ->icon('heroicon-s-star')
                        ->color('warning')
                        ->label('Mark as Top Destinations')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['is_top_destination' => true]);

                            Notification::make()
                                ->title("Marked {$count} states as top destinations")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('removeFromTopDestination')
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->label('Remove from Top Destinations')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['is_top_destination' => false]);

                            Notification::make()
                                ->title("Removed {$count} states from top destinations")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->update(['status' => 'active']);

                            Notification::make()
                                ->title("Activated {$count} states")
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
                                ->title("Deactivated {$count} states")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No States Found')
            ->emptyStateDescription('Create your first state to get started.')
            ->emptyStateIcon('heroicon-o-map')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add State')
                    ->url(route('filament.admin.resources.states.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->paginated([25, 50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ZellasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStates::route('/'),
            'create' => Pages\CreateState::route('/create'),
            'view' => Pages\ViewState::route('/{record}'),
            'edit' => Pages\EditState::route('/{record}/edit'),
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
        return ['name', 'code', 'country.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Country' => $record->country->name,
            'Status' => ucfirst($record->status),
        ];
    }
}
