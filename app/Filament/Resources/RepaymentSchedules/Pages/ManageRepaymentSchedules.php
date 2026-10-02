<?php

namespace App\Filament\Resources\RepaymentSchedules\Pages;

use App\Filament\Resources\RepaymentSchedules\RepaymentScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRepaymentSchedules extends ManageRecords
{
    protected static string $resource = RepaymentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
