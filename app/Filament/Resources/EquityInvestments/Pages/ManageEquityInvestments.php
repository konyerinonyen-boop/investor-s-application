<?php

namespace App\Filament\Resources\EquityInvestments\Pages;

use App\Filament\Resources\EquityInvestments\EquityInvestmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEquityInvestments extends ManageRecords
{
    protected static string $resource = EquityInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
