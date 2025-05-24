<?php

namespace App\Filament\Resources\StateResource\Pages;

use App\Filament\Resources\StateResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewState extends ViewRecord
{
    protected static string $resource = StateResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('State Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('country.name')
                            ->label('Country'),

                        Infolists\Components\TextEntry::make('code')
                            ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : '—'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                default => 'danger',
                            }),
                    ])->columns(2),

                Infolists\Components\Section::make('Media')
                    ->schema([
                        Infolists\Components\ImageEntry::make('image')
                            ->visibility(fn ($record) => !empty($record->image)),
                    ]),

                Infolists\Components\Section::make('Related Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('zellas_count')
                            ->label('Number of Zellas')
                            ->state(function ($record) {
                                return $record->zellas()->count();
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])->columns(3),
            ]);
    }
}
