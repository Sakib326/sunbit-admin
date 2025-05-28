<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourCategoryResource\Pages;
use App\Filament\Resources\TourCategoryResource\RelationManagers;
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

class TourCategoryResource extends Resource
{
    protected static ?string $model = TourCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Tour Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->description('Main category details')
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

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])->columns(2),
                    ]),

                Section::make('SEO Information')
                    ->description('Search engine optimization settings')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(255)
                                    ->placeholder('Leave empty to use category name')
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
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (TourCategory $record): string => $record->slug)
                    ->limit(30),

                Tables\Columns\TextColumn::make('packages_count')
                    ->counts('packages')
                    ->label('Tour Packages')
                    ->sortable()
                    ->badge(),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->getStateUsing(fn (TourCategory $record): bool => $record->status === 'active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('meta_title')
                    ->label('SEO Title')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('meta_description')
                    ->label('SEO Description')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(50),

                Tables\Columns\TextColumn::make('meta_keywords')
                    ->label('SEO Keywords')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ? Str::limit($state, 30) : null),

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
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->attribute('status'),

                Tables\Filters\Filter::make('with_packages')
                    ->label('Has Tour Packages')
                    ->query(fn (Builder $query): Builder => $query->has('packages')),

                Tables\Filters\Filter::make('without_packages')
                    ->label('No Tour Packages')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('packages')),

                Tables\Filters\Filter::make('created_recently')
                    ->label('Created Recently')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7))),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggleStatus')
                        ->icon(fn (TourCategory $record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->label(fn (TourCategory $record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (TourCategory $record) => $record->status === 'active' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (TourCategory $record) {
                            $record->status = $record->status === 'active' ? 'inactive' : 'active';
                            $record->save();

                            Notification::make()
                                ->title($record->name . ' ' . ($record->status === 'active' ? 'activated' : 'deactivated'))
                                ->success()
                                ->send();
                        }),
                    // Tables\Actions\Action::make('viewTours')
                    //     ->label('View Tour Packages')
                    //     ->icon('heroicon-o-clipboard-document-list')
                    //     ->url(fn (TourCategory $record): string => route('filament.admin.resources.tour-packages.index', ['tableFilters[category][value]' => $record->id])),
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
                                ->title("{$count} categories activated")
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
                                ->title("{$count} categories deactivated")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Tour Categories')
            ->emptyStateDescription('Create categories to organize your tour packages')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Tour Category')
                    ->url(route('filament.admin.resources.tour-categories.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PackagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTourCategories::route('/'),
            'create' => Pages\CreateTourCategory::route('/create'),
            'view' => Pages\ViewTourCategory::route('/{record}'),
            'edit' => Pages\EditTourCategory::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'meta_keywords'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Status' => ucfirst($record->status),
            'Tour Packages' => $record->packages()->count(),
        ];
    }
}
