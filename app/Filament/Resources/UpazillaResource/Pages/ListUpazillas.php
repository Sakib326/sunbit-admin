<?php

namespace App\Filament\Resources\UpazillaResource\Pages;

use App\Filament\Resources\UpazillaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUpazillas extends ListRecords
{
    protected static string $resource = UpazillaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
