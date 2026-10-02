<?php

namespace App\Filament\Resources\KycProfiles\Pages;

use App\Filament\Resources\KycProfiles\KycProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKycProfiles extends ManageRecords
{
    protected static string $resource = KycProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
