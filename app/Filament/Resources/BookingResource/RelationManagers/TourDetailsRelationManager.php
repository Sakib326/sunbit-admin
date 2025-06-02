<?php

// filepath: app/Filament/Resources/BookingResource/RelationManagers/TourDetailsRelationManager.php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TourDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'tourDetails';
    protected static ?string $title = 'Tour Details';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tour_package_id')
                    ->relationship('tourPackage', 'title')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('pickup_location')
                    ->maxLength(255),

                Forms\Components\TimePicker::make('pickup_time')
                    ->default('08:00'),

                Forms\Components\TextInput::make('drop_location')
                    ->maxLength(255),

                Forms\Components\Select::make('room_type')
                    ->options([
                        'single' => 'Single Room',
                        'twin' => 'Twin Sharing',
                        'triple' => 'Triple Sharing',
                        'family' => 'Family Room'
                    ])
                    ->default('twin'),

                Forms\Components\Select::make('meal_plan')
                    ->options([
                        'no_meals' => 'No Meals',
                        'breakfast' => 'Breakfast Only',
                        'half_board' => 'Half Board',
                        'full_board' => 'Full Board'
                    ])
                    ->default('breakfast'),

                Forms\Components\TextInput::make('guide_language')
                    ->default('English'),

                Forms\Components\TextInput::make('emergency_contact')
                    ->tel(),

                Forms\Components\Textarea::make('tour_notes')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tourPackage.title')
                    ->label('Tour Package'),

                Tables\Columns\TextColumn::make('pickup_location'),

                Tables\Columns\TextColumn::make('pickup_time')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('room_type_label')
                    ->label('Room Type'),

                Tables\Columns\TextColumn::make('meal_plan_label')
                    ->label('Meal Plan'),

                Tables\Columns\TextColumn::make('guide_language'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => !$this->ownerRecord->tourDetails),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
