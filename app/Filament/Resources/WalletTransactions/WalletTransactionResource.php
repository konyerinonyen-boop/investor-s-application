<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Filament\Resources\WalletTransactions\Pages\ManageWalletTransactions;
use App\Filament\Support\AdminResource;
use App\Models\WalletTransaction;
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

class WalletTransactionResource extends AdminResource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup =
        'Investment Operations';

    protected static ?string $navigationLabel = 'Wallet Transactions';

    protected static ?string $modelLabel = 'Wallet Transaction';

    protected static ?string $pluralModelLabel = 'Wallet Transactions';

    protected static ?string $permissionResource = 'wallet transactions';

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaction Information')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columns(2)
                ->schema([
                    TextEntry::make('id')
                        ->label('Transaction ID'),

                    TextEntry::make('reference')
                        ->label('Transaction Reference')
                        ->copyable(),

                    TextEntry::make('wallet.user.full_name')
                        ->label('Investor')
                        ->placeholder('Investor unavailable'),

                    TextEntry::make('wallet.user.email')
                        ->label('Investor Email')
                        ->copyable(),

                    TextEntry::make('type')
                        ->label('Transaction Type')
                        ->badge(),

                    TextEntry::make('direction')
                        ->label('Direction')
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'credit' => 'success',
                            'debit' => 'danger',
                            default => 'gray',
                        }),

                    TextEntry::make('amount')
                        ->label('Transaction Amount')
                        ->money('NGN')
                        ->weight('bold'),

                    TextEntry::make('balance_after')
                        ->label('Wallet Balance After Transaction')
                        ->money('NGN'),

                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('No description provided')
                        ->columnSpanFull(),

                    TextEntry::make('created_at')
                        ->label('Transaction Date')
                        ->dateTime('d M Y, H:i:s'),
                ]),

            Section::make('Source and Metadata')
                ->description('The system record associated with this financial event.')
                ->schema([
                    TextEntry::make('source_type')
                        ->label('Source Type')
                        ->placeholder('No source linked'),

                    TextEntry::make('source_id')
                        ->label('Source Record ID')
                        ->placeholder('Not linked'),

                    TextEntry::make('metadata')
                        ->label('Transaction Metadata')
                        ->formatStateUsing(fn ($state): string =>
                            is_array($state)
                                ? json_encode(
                                    $state,
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                                )
                                : (string) ($state ?? 'No metadata')
                        )
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query->with('wallet.user')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('wallet.user.full_name')
                    ->label('Investor')
                    ->description(fn (WalletTransaction $record): ?string =>
                        $record->wallet?->user?->email
                    )
                    ->searchable(query: function (
                        Builder $query,
                        string $search
                    ): Builder {
                        return $query->whereHas(
                            'wallet.user',
                            fn (Builder $userQuery) => $userQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                        );
                    }),

                TextColumn::make('type')
                    ->label('Transaction Type')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('direction')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        ucfirst($state)
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->money('NGN')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->limit(24),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(35)
                    ->placeholder('—')
                    ->toggleable(),

            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options([
                        'credit' => 'Credits',
                        'debit' => 'Debits',
                    ]),

                SelectFilter::make('type')
                    ->options(fn (): array =>
                        WalletTransaction::query()
                            ->distinct()
                            ->orderBy('type')
                            ->pluck('type', 'type')
                            ->all()
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Inspect')
                    ->icon(Heroicon::OutlinedEye),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No wallet transactions found')
            ->emptyStateDescription(
                'Wallet credits and debits will appear here as investors use the platform.'
            )
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWalletTransactions::route('/'),
        ];
    }
}