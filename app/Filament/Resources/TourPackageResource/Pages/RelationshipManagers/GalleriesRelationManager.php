<?php

namespace App\Filament\Resources\TourPackageResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class GalleriesRelationManager extends RelationManager
{
    protected static string $relationship = 'galleries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('image_url')
                ->label('Image')
                ->image()
                ->directory('tour-gallery')
                ->visibility('public')
                ->required()
                ->maxSize(5120)
                ->deleteUploadedFileUsing(function ($file) {
                    if ($file && file_exists(storage_path('app/public/' . $file))) {
                        unlink(storage_path('app/public/' . $file));
                    }
                }),

                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured Image')
                    ->helperText('Mark as main gallery image'),

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

                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->square(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->label('Featured'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['position'] = $this->getOwnerRecord()->galleries()->max('position') + 1;
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
            ->emptyStateHeading('No Gallery Images')
            ->emptyStateDescription('Add images to showcase this tour package')
            ->emptyStateIcon('heroicon-o-photo');
    }
}
