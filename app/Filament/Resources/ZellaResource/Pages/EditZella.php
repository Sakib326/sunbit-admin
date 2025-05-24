<?php

namespace App\Filament\Resources\ZellaResource\Pages;

use App\Filament\Resources\ZellaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditZella extends EditRecord
{
    protected static string $resource = ZellaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
