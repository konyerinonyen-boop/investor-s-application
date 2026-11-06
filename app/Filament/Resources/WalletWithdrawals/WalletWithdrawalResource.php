<?php

namespace App\Filament\Resources\WalletWithdrawals;

use App\Filament\Resources\WalletWithdrawals\Pages\ManageWalletWithdrawals;
use App\Filament\Support\AdminResource;
use App\Models\WalletWithdrawal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WalletWithdrawalResource extends AdminResource
{
    protected static ?string $model = WalletWithdrawal::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup =
        'Investment Operations';

    protected static ?string $navigationLabel = 'Wallet Withdrawals';

    protected static ?string $modelLabel = 'Wallet Withdrawal';

    protected static ?string $pluralModelLabel = 'Wallet Withdrawals';

    protected static ?string $permissionResource = 'wallet withdrawals';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'processing' => 'warning',
            'requested' => 'info',
            'rejected', 'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Withdrawal Overview')
                ->icon(Heroicon::OutlinedBanknotes)
                ->columns(2)
                ->schema([
                    TextEntry::make('id')
                        ->label('Withdrawal ID'),

                    TextEntry::make('user.full_name')
                        ->label('Investor'),

                    TextEntry::make('user.email')
                        ->label('Investor Email')
                        ->copyable(),

                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (?string $state): string =>
                            self::statusColor($state)
                        ),

                    TextEntry::make('amount')
                        ->label('Withdrawal Amount')
                        ->money('NGN')
                        ->weight('bold'),

                    TextEntry::make('fee')
                        ->label('Withdrawal Fee')
                        ->money('NGN'),

                    TextEntry::make('total_debit')
                        ->label('Total Wallet Debit')
                        ->money('NGN')
                        ->weight('bold'),

                    TextEntry::make('currency')
                        ->badge(),

                    TextEntry::make('created_at')
                        ->label('Requested At')
                        ->dateTime('d M Y, H:i'),

                    TextEntry::make('transferred_at')
                        ->label('Transferred At')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Not confirmed'),
                ]),

            Section::make('Destination Bank Account')
                ->description('Bank account details saved when the withdrawal was requested.')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->columns(2)
                ->schema([
                    TextEntry::make('bank_name')
                        ->label('Bank'),

                    TextEntry::make('bank_code')
                        ->label('Bank Code'),

                    TextEntry::make('account_name')
                        ->label('Account Name'),

                    TextEntry::make('account_number')
                        ->label('Account Number')
                        ->formatStateUsing(fn (?string $state): string =>
                            $state
                                ? '••••••' . substr($state, -4)
                                : 'Not available'
                        ),
                ]),

            Section::make('Transfer Reconciliation')
                ->description('Provider references and the recorded transfer outcome.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->columns(2)
                ->schema([
                    TextEntry::make('transfer_reference')
                        ->label('Korapay Transfer Reference')
                        ->copyable()
                        ->placeholder('Not available'),

                    TextEntry::make('recipient_code')
                        ->label('Recipient Code')
                        ->copyable()
                        ->placeholder('Not available'),

                    TextEntry::make('walletTransaction.reference')
                        ->label('Wallet Debit Reference')
                        ->copyable()
                        ->placeholder('Not linked'),

                    TextEntry::make('failure_reason')
                        ->label('Failure / Reconciliation Note')
                        ->placeholder('No failure recorded')
                        ->columnSpanFull(),

                    TextEntry::make('provider_response')
                        ->label('Provider Response')
                        ->formatStateUsing(fn ($state): string =>
                            is_array($state)
                                ? json_encode(
                                    $state,
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                                )
                                : (string) ($state ?? 'No provider response stored')
                        )
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with(['user', 'walletTransaction'])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.full_name')
                    ->label('Investor')
                    ->description(fn (WalletWithdrawal $record): ?string =>
                        $record->user?->email
                    )
                    ->searchable(query: function (
                        Builder $query,
                        string $search
                    ): Builder {
                        return $query->whereHas(
                            'user',
                            fn (Builder $userQuery) => $userQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                        );
                    }),

                TextColumn::make('amount')
                    ->label('Withdrawal')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('fee')
                    ->label('Fee')
                    ->money('NGN')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_debit')
                    ->label('Total Debited')
                    ->money('NGN')
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('bank_name')
                    ->label('Destination Bank')
                    ->searchable(),

                TextColumn::make('account_number')
                    ->label('Account')
                    ->formatStateUsing(fn (?string $state): string =>
                        $state
                            ? '••••' . substr($state, -4)
                            : '—'
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string =>
                        self::statusColor($state)
                    )
                    ->sortable(),

                TextColumn::make('transfer_reference')
                    ->label('Transfer Reference')
                    ->copyable()
                    ->limit(20)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'processing' => 'Processing',
                        'paid' => 'Paid',
                        'rejected' => 'Rejected',
                        'failed' => 'Failed',
                    ]),

                SelectFilter::make('currency')
                    ->options([
                        'NGN' => 'Nigerian Naira',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Inspect')
                    ->icon(Heroicon::OutlinedEye),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No withdrawal records found')
            ->emptyStateDescription(
                'Investor withdrawal requests and their transfer outcomes will appear here.'
            )
            ->emptyStateIcon(Heroicon::OutlinedBuildingLibrary);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWalletWithdrawals::route('/'),
        ];
    }
}