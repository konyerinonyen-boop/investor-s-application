<?php

namespace App\Filament\Resources\RepaymentPayouts\Pages;

use App\Filament\Resources\RepaymentPayouts\RepaymentPayoutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRepaymentPayout extends EditRecord
{
    protected static string $resource = RepaymentPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
