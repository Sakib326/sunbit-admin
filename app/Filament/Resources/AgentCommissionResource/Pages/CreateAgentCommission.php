<?php

namespace App\Filament\Resources\AgentCommissionResource\Pages;

use App\Filament\Resources\AgentCommissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentCommission extends CreateRecord
{
    protected static string $resource = AgentCommissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        return $data;
    }
}
