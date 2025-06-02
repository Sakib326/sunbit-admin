<?php

// filepath: app/Filament/Resources/CarRentalPackageResource/Pages/ViewCarRentalPackage.php

namespace App\Filament\Resources\CarRentalPackageResource\Pages;

use App\Filament\Resources\CarRentalPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCarRentalPackage extends ViewRecord
{
    protected static string $resource = CarRentalPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
