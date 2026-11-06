<?php

namespace App\Filament\Resources\Wallets;

use App\Filament\Resources\Wallets\Pages\ManageWallets;
use App\Filament\Resources\Wallets\Pages\ViewWallet;
use App\Filament\Support\AdminResource;
use App\Models\Wallet;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WalletResource extends AdminResource
{
    protected static ?string $model = Wallet::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup =
        'Investment Operations';

    protected static ?string $navigationLabel = 'Investor Wallets';

    protected static ?string $modelLabel = 'Wallet';

    protected static ?string $pluralModelLabel = 'Investor Wallets';

    protected static ?string $permissionResource = 'wallets';

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
            Section::make('Wallet Information')
                ->description('The investor account and its current wallet balance.')
                ->icon(Heroicon::OutlinedWallet)
                ->columns(2)
                ->schema([
                    TextEntry::make('id')
                        ->label('Wallet ID')
                        ->copyable(),

                    TextEntry::make('user.full_name')
                        ->label('Investor')
                        ->placeholder('Investor unavailable'),

                    TextEntry::make('user.email')
                        ->label('Email address')
                        ->copyable(),

                    TextEntry::make('user.phone')
                        ->label('Phone number')
                        ->placeholder('Not provided'),

                    TextEntry::make('currency')
                        ->label('Currency')
                        ->badge(),

                    TextEntry::make('balance')
                        ->label('Available Balance')
                        ->money('NGN')
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('created_at')
                        ->label('Wallet Created')
                        ->dateTime('d M Y, H:i'),

                    TextEntry::make('updated_at')
                        ->label('Last Activity')
                        ->dateTime('d M Y, H:i'),
                ]),

            Section::make('Wallet Activity')
                ->description('Summary of transactions recorded against this wallet.')
                ->schema([
                    TextEntry::make('transactions_count')
                        ->label('Total Transactions')
                        ->state(fn (Wallet $record): int => $record->transactions()->count()
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with('user')
                    ->withCount('transactions')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Wallet ID')
                    ->sortable(),

                TextColumn::make('user.full_name')
                    ->label('Investor')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Wallet $record): ?string => $record->user?->email
                    )
                    ->weight('medium')
                    ->sortable(),

                TextColumn::make('balance')
                    ->label('Available Balance')
                    ->money('NGN')
                    ->sortable()
                    ->weight('semibold')
                    ->color(fn ($state): string => (float) $state > 0 ? 'success' : 'gray'
                    ),

                TextColumn::make('currency')
                    ->badge()
                    ->sortable(),

                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('currency')
                    ->options([
                        'NGN' => 'Nigerian Naira',
                    ]),

                Filter::make('has_balance')
                    ->label('Wallets with funds')
                    ->query(
                        fn (Builder $query): Builder => $query->where('balance', '>', 0)
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('View wallet')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Wallet $record): string => static::getUrl('view', ['record' => $record])
                    ),
            ])
            ->toolbarActions([])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->emptyStateHeading('No investor wallets found')
            ->emptyStateDescription(
                'Wallets will appear here when investors receive or make their first wallet transaction.'
            )
            ->emptyStateIcon(Heroicon::OutlinedWallet);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWallets::route('/'),
            'view' => ViewWallet::route('/{record}'),
        ];
    }
}
