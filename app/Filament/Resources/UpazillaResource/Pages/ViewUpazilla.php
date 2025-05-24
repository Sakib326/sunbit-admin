<?php

namespace App\Filament\Resources\UpazillaResource\Pages;

use App\Filament\Resources\UpazillaResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewUpazilla extends ViewRecord
{
    protected static string $resource = UpazillaResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Complete Location Hierarchy')
                    ->description('The full geographical context of this upazilla')
                    ->schema([
                        Infolists\Components\TextEntry::make('zella.state.country.name')
                            ->label('Country'),

                        Infolists\Components\TextEntry::make('zella.state.name')
                            ->label('State'),

                        Infolists\Components\TextEntry::make('zella.name')
                            ->label('Zella'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Upazilla')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    ])->columns(4),

                Infolists\Components\Section::make('Upazilla Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : '—'),

                        Infolists\Components\TextEntry::make('postal_code')
                            ->label('Postal/ZIP Code')
                            ->placeholder('Not specified'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                default => 'danger',
                            }),
                    ])->columns(3),

                Infolists\Components\Section::make('Record Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('deleted_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->deleted_at !== null),
                    ])->columns(3),
            ]);
    }
}
