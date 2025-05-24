<?php

namespace App\Filament\Resources\UpazillaResource\Pages;

use App\Filament\Resources\UpazillaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUpazilla extends EditRecord
{
    protected static string $resource = UpazillaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
