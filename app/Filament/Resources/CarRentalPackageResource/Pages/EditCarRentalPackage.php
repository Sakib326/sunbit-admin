<?php

// filepath: app/Filament/Resources/CarRentalPackageResource/Pages/EditCarRentalPackage.php

namespace App\Filament\Resources\CarRentalPackageResource\Pages;

use App\Filament\Resources\CarRentalPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCarRentalPackage extends EditRecord
{
    protected static string $resource = CarRentalPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Car rental package updated successfully';
    }
}
