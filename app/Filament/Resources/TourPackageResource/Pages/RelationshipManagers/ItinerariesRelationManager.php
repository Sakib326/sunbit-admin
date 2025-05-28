<?php

namespace App\Filament\Resources\TourPackageResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItinerariesRelationManager extends RelationManager
{
    protected static string $relationship = 'itineraries';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Day 1, Morning Session'),

                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. City Tour & Museums'),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->placeholder('Detailed description of this itinerary item...'),

                    Forms\Components\FileUpload::make('image')
                    ->image()
                    ->directory('itinerary-images')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->deleteUploadedFileUsing(function ($file) {
                        if ($file && file_exists(storage_path('app/public/' . $file))) {
                            unlink(storage_path('app/public/' . $file));
                        }
                    }),

                Forms\Components\TextInput::make('position')
                    ->numeric()
                    ->default(1)
                    ->helperText('Order position (1 = first)')
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\ImageColumn::make('image')
                    ->circular()
                    ->visibility(fn ($record) => !empty($record->image)),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['position'] = $this->getOwnerRecord()->itineraries()->max('position') + 1;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Itinerary Items')
            ->emptyStateDescription('Add itinerary items to show the day-by-day schedule')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
