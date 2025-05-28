<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentCommissionResource\Pages;
use App\Models\AgentCommission;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgentCommissionResource extends Resource
{
    protected static ?string $model = AgentCommission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Agent Management';
    protected static ?string $navigationLabel = 'Agent Commissions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service')
                    ->label('Service')
                    ->options(array_combine(AgentCommission::SERVICES, AgentCommission::SERVICES))
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('agent_id')
                    ->label('Agent')
                    ->relationship(
                        name: 'agent',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('role', 'agent')
                    )
                    ->searchable(),

                Forms\Components\TextInput::make('commission_percent')
                    ->label('Commission (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->nullable()
                    ->helperText('Percentage commission. If both percent and amount are set, percent is used.'),

                Forms\Components\TextInput::make('commission_amount')
                    ->label('Commission (Fixed)')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Fixed commission amount. Used only if percent is not set.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('service')
                    ->label('Service')
                    ->colors([
                        'primary' => 'FLIGHT',
                        'info' => 'HOTEL',
                        'success' => 'TRANSFER',
                        'warning' => 'TOURS',
                        'danger' => 'CRUISE',
                        'gray' => 'TRANSPORT',
                        'secondary' => 'VISA',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agent')
                    ->default('All Agents')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('commission_percent')
                    ->label('Percent')
                    ->formatStateUsing(fn ($state) => $state !== null ? $state . '%' : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Fixed')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updater.name')
                    ->label('Updated By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service')
                    ->options(array_combine(AgentCommission::SERVICES, AgentCommission::SERVICES)),
                Tables\Filters\SelectFilter::make('agent_id')
                    ->label('Agent')
                    ->relationship('agent', 'name')
                    ->searchable(),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentCommissions::route('/'),
            'create' => Pages\CreateAgentCommission::route('/create'),
            'edit' => Pages\EditAgentCommission::route('/{record}/edit'),
        ];
    }
}
