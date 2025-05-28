<?php

namespace App\Filament\Resources\TourCategoryResource\Pages;

use App\Filament\Resources\TourCategoryResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTourCategory extends ViewRecord
{
    protected static string $resource = TourCategoryResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Category Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('slug')
                            ->icon('heroicon-o-link'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                default => 'danger',
                            }),

                        Infolists\Components\TextEntry::make('packages_count')
                            ->label('Tour Packages')
                            ->state(function ($record) {
                                return $record->packages()->count();
                            }),
                    ])->columns(2),

                Infolists\Components\Section::make('SEO Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('meta_title')
                            ->label('Meta Title')
                            ->placeholder('Not specified'),

                        Infolists\Components\TextEntry::make('meta_description')
                            ->label('Meta Description')
                            ->placeholder('Not specified'),

                        Infolists\Components\TextEntry::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->placeholder('No keywords specified')
                            ->formatStateUsing(fn ($state) => $state ? str_replace(',', ', ', $state) : null),
                    ]),

                Infolists\Components\Section::make('Record Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Modified')
                            ->dateTime(),
                    ])->columns(2),
            ]);
    }
}
