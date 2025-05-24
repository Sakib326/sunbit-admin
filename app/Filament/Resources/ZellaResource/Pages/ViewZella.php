<?php

namespace App\Filament\Resources\ZellaResource\Pages;

use App\Filament\Resources\ZellaResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewZella extends ViewRecord
{
    protected static string $resource = ZellaResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Location Hierarchy')
                    ->schema([
                        Infolists\Components\TextEntry::make('state.country.name')
                            ->label('Country'),

                        Infolists\Components\TextEntry::make('state.name')
                            ->label('State'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Zella')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    ])->columns(3),

                Infolists\Components\Section::make('Zella Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : '—'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                default => 'danger',
                            }),

                        Infolists\Components\TextEntry::make('upazillas_count')
                            ->label('Number of Upazillas')
                            ->state(function ($record) {
                                return $record->upazillas()->count();
                            }),
                    ])->columns(3),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])->columns(2),
            ]);
    }
}
