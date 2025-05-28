<?php

namespace App\Filament\Resources\AgentCommissionResource\Pages;

use App\Filament\Resources\AgentCommissionResource;
use Filament\Resources\Pages\EditRecord;

class EditAgentCommission extends EditRecord
{
    protected static string $resource = AgentCommissionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}
