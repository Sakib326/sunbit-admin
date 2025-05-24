<?php

namespace App\Filament\Resources\ZellaResource\Pages;

use App\Filament\Resources\ZellaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZellas extends ListRecords
{
    protected static string $resource = ZellaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
