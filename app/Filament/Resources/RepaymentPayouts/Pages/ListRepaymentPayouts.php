<?php

namespace App\Filament\Resources\RepaymentPayouts\Pages;

use App\Filament\Resources\RepaymentPayouts\RepaymentPayoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRepaymentPayouts extends ListRecords
{
    protected static string $resource = RepaymentPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
