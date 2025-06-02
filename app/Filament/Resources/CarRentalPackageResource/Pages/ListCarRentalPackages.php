<?php

// filepath: app/Filament/Resources/CarRentalPackageResource/Pages/ListCarRentalPackages.php

namespace App\Filament\Resources\CarRentalPackageResource\Pages;

use App\Filament\Resources\CarRentalPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCarRentalPackages extends ListRecords
{
    protected static string $resource = CarRentalPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }
}
