<?php

namespace App\Filament\Resources\DividendDeclarations\Pages;

use App\Filament\Resources\DividendDeclarations\DividendDeclarationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDividendDeclarations extends ManageRecords
{
    protected static string $resource = DividendDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge($data, [
            'created_by' => auth()->id(),
            'currency' => 'NGN',
            'status' => 'draft',
            'total_eligible_units' => '0.000000',
            'total_allocated' => '0.00',
        ]);
    }
}
