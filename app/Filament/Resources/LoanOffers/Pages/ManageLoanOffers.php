<?php

namespace App\Filament\Resources\LoanOffers\Pages;

use App\Filament\Resources\LoanOffers\LoanOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLoanOffers extends ManageRecords
{
    protected static string $resource = LoanOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
