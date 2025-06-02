<?php

// filepath: app/Filament/Resources/BookingResource/RelationManagers/CarRentalDetailsRelationManager.php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CarRentalDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'carRentalDetails';
    protected static ?string $title = 'Car Rental Details';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('car_rental_package_id')
                    ->label('Car Package')
                    ->relationship('carRentalPackage', 'title')
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('pickup_date')
                            ->required(),
                        Forms\Components\DatePicker::make('return_date')
                            ->required(),
                    ]),

                Forms\Components\TextInput::make('rental_days')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pickup_date')
            ->columns([
                Tables\Columns\TextColumn::make('carRentalPackage.title')
                    ->label('Car Package'),
                Tables\Columns\TextColumn::make('pickup_date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('return_date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('rental_days')
                    ->label('Days'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->service_type === 'CAR_RENTAL'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->service_type !== 'CAR_RENTAL';
    }
}
