<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWalletTransactions extends ManageRecords
{
    protected static string $resource = WalletTransactionResource::class;
}