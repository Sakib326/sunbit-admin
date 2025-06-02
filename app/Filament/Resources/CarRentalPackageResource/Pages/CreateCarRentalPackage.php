<?php

// filepath: app/Filament/Resources/CarRentalPackageResource/Pages/CreateCarRentalPackage.php

namespace App\Filament\Resources\CarRentalPackageResource\Pages;

use App\Filament\Resources\CarRentalPackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCarRentalPackage extends CreateRecord
{
    protected static string $resource = CarRentalPackageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Car rental package created successfully';
    }
}
