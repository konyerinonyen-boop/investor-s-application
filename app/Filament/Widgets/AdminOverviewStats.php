<?php

namespace App\Filament\Widgets;

use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Investor accounts', User::query()->count())
                ->description('Registered platform members')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('info'),
            Stat::make('Open opportunities', Product::query()->where('status', 'active')->count())
                ->description('Currently available products')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('success'),
            Stat::make('KYC reviews', KycProfile::query()->where('status', 'pending')->count())
                ->description('Awaiting a compliance decision')
                ->descriptionIcon(Heroicon::OutlinedIdentification)
                ->color('warning'),
            Stat::make('Payments in progress', Payment::query()->whereIn('status', ['pending', 'processing'])->count())
                ->description('Pending or processing')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('danger'),
        ];
    }
}
