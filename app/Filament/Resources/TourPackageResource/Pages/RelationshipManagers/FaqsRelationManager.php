<?php

namespace App\Filament\Resources\TourPackageResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FaqsRelationManager extends RelationManager
{
    protected static string $relationship = 'faqs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('question')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Frequently asked question...'),

                Forms\Components\Textarea::make('answer')
                    ->required()
                    ->rows(3)
                    ->placeholder('Detailed answer to the question...'),

                Forms\Components\TextInput::make('position')
                    ->numeric()
                    ->default(1)
                    ->helperText('Display order (1 = first)')
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('question')
                    ->searchable()
                    ->limit(50)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('answer')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['position'] = $this->getOwnerRecord()->faqs()->max('position') + 1;
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
            ->emptyStateHeading('No FAQs')
            ->emptyStateDescription('Add frequently asked questions about this tour')
            ->emptyStateIcon('heroicon-o-question-mark-circle');
    }
}
