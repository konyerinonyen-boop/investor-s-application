<?php

namespace App\Filament\Resources\KycProfiles\Pages;

use App\Filament\Resources\KycProfiles\KycProfileResource;
use App\Models\KycProfile;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewKycProfile extends ViewRecord
{
    protected static string $resource = KycProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSumsub')
                ->label('Open Applicant in Sumsub')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('primary')
                ->url(fn (KycProfile $record): ?string =>
                    KycProfileResource::getSumsubApplicantUrl($record)
                )
                ->openUrlInNewTab()
                ->visible(fn (KycProfile $record): bool =>
                    KycProfileResource::getSumsubApplicantUrl($record) !== null
                ),
        ];
    }
}
