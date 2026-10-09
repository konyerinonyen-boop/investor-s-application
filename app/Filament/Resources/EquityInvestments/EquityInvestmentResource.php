<?php

namespace App\Filament\Resources\EquityInvestments;

use App\Filament\Resources\EquityInvestments\Pages\ManageEquityInvestments;
use App\Filament\Resources\EquityInvestments\Pages\ViewEquityInvestment;
use App\Filament\Support\AdminResource;
use App\Models\EquityInvestment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

class EquityInvestmentResource extends AdminResource
{
    protected static ?string $model = EquityInvestment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Investment Operations';

    protected static ?string $permissionResource = 'equity investments';


        /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    #[Override]
    public static function canCreate(): bool
    {
        return false;
    }
    #[Override]
    public static function canDeleteAny(): bool
    {
        return false;
    }

    #[Override]
    public static function canDelete(Model $record): bool
    {
        return false;
    }
public static function infolist(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Investment Overview')
                ->description('A summary of the investor’s equity commitment.')
                ->icon('heroicon-o-chart-pie')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('full_name')
                                ->label('Full Name')
                                ->weight('bold')
                                ->state(fn (EquityInvestment $record): string => trim(
                                    ($record->investor?->first_name ?? '') . ' ' .
                                    ($record->investor?->last_name ?? '')
                                )),

                            TextEntry::make('round.name')
                                ->label('Equity Round')
                                ->icon('heroicon-o-briefcase')
                                ->placeholder('Round not available'),

                            TextEntry::make('amount')
                                ->label('Investment Amount')
                                ->money('USD')
                                ->weight('bold')
                                ->color('success'),

                            TextEntry::make('units')
                                ->label('Equity Units')
                                ->numeric(decimalPlaces: 6)
                                ->suffix(' units')
                                ->icon('heroicon-o-chart-bar'),
                        ]),
                ]),

            Section::make('Investment Status')
                ->description('Current progress and commitment status.')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('status')
                                ->label('Current Status')
                                ->badge()
                                ->formatStateUsing(
                                    fn (?string $state): string => match ($state) {
                                        'pending' => 'Pending',
                                        'pending_payment' => 'Awaiting Payment',
                                        'committed' => 'Committed',
                                        'funded' => 'Funded',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                        default => str($state ?? 'Unknown')
                                            ->replace('_', ' ')
                                            ->title()
                                            ->toString(),
                                    }
                                )
                                ->color(fn (?string $state): string => match ($state) {
                                    'funded', 'completed' => 'success',
                                    'committed' => 'info',
                                    'pending', 'pending_payment' => 'warning',
                                    'cancelled' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('created_at')
                                ->label('Investment Date')
                                ->dateTime('M j, Y \a\t g:i A')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime('M j, Y \a\t g:i A')
                                ->since()
                                ->placeholder('Not available'),
                        ]),
                ]),

            Section::make('Payment & Documentation')
                ->description('Payment tracking and associated agreement details.')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('payment_reference')
                                ->label('Payment Reference')
                                ->icon('heroicon-o-credit-card')
                                ->copyable()
                                ->copyMessage('Payment reference copied')
                                ->placeholder('No payment reference'),

                            TextEntry::make('agreement_id')
                                ->label('Agreement Reference')
                                ->icon('heroicon-o-document-check')
                                ->copyable()
                                ->copyMessage('Agreement reference copied')
                                ->placeholder('No agreement attached'),
                        ]),
                ]),
        ]);
}


public static function table(Table $table): Table
{
    return $table
        ->defaultSort('created_at', 'desc')
        ->columns([
                   TextColumn::make('full_name')
                                ->label('Full Name')
                                ->weight('bold')
                                ->state(fn (EquityInvestment $record): string => trim(
                                    ($record->investor?->first_name ?? '') . ' ' .
                                    ($record->investor?->last_name ?? '')
                                ))
                ->description(fn ($record) => $record->investor?->email),

            TextColumn::make('round.name')
                ->label('Equity Round')
                ->searchable()
                ->sortable()
                ->placeholder('Round unavailable'),

            TextColumn::make('amount')
                ->label('Amount')
                ->money('USD')
                ->sortable()
                ->weight('bold')
                ->color('success'),

            TextColumn::make('units')
                ->label('Equity Units')
                ->numeric(decimalPlaces: 6)
                ->sortable()
                ->alignEnd(),

            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'pending' => 'Pending',
                    'pending_payment' => 'Awaiting Payment',
                    'committed' => 'Committed',
                    'funded' => 'Funded',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    default => str($state ?? 'Unknown')
                        ->replace('_', ' ')
                        ->title()
                        ->toString(),
                })
                ->color(fn (?string $state): string => match ($state) {
                    'completed', 'funded' => 'success',
                    'committed' => 'info',
                    'pending', 'pending_payment' => 'warning',
                    'cancelled' => 'danger',
                    default => 'gray',
                })
                ->sortable(),

            TextColumn::make('payment_reference')
                ->label('Payment Reference')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->placeholder('—'),

            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('M j, Y')
                ->since()
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->label('Investment Status')
                ->options([
                    'pending' => 'Pending',
                    'pending_payment' => 'Awaiting Payment',
                    'committed' => 'Committed',
                    'funded' => 'Funded',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ]),

            SelectFilter::make('equity_round_id')
                ->label('Equity Round')
                ->relationship('round', 'name')
                ->searchable()
                ->preload(),
        ])
        ->recordActions([
            EditAction::make(),
            DeleteAction::make()
                ->visible(fn ($record): bool => in_array(
                    $record->status,
                    ['pending', 'pending_payment', 'cancelled'],
                    true
                )),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->visible(false),
            ]),
        ])
        ->emptyStateHeading('No equity investments yet')
        ->emptyStateDescription(
            'Investment records will appear here when investors submit their commitments.'
        )
        ->emptyStateIcon('heroicon-o-chart-pie');
}

    public static function getPages(): array
    {
        return [
            'index' => ManageEquityInvestments::route('/'),
            'view' => ViewEquityInvestment::route('/{record}'),
        ];
    }
}
