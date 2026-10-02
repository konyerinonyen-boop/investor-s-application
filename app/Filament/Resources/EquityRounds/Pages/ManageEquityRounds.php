<?php

namespace App\Filament\Resources\EquityRounds\Pages;

use App\Filament\Resources\EquityRounds\EquityRoundResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEquityRounds extends ManageRecords
{
    protected static string $resource = EquityRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
