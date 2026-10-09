<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\EquityInvestments\Pages\ViewEquityInvestment;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Support\AdminResource;
use App\Models\Payment;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Infolists\Components\TextEntry;

class PaymentResource extends AdminResource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Investment Operations';

    protected static ?string $permissionResource = 'payments';

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
        return $schema
            ->components([
                Section::make('Payment Details')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Payment ID'),

                        TextEntry::make('user.first_name')
                            ->label('Investor First Name'),

                        TextEntry::make('user.last_name')
                            ->label('Investor Last Name'),

                        TextEntry::make('user.email')
                            ->label('Investor Email'),

                        TextEntry::make('amount')
                            ->numeric(decimalPlaces: 2),

                        TextEntry::make('currency')
                            ->badge(),

                        TextEntry::make('provider')
                            ->label('Payment Provider')
                            ->badge(),

                        TextEntry::make('provider_reference')
                            ->label('Provider Reference')
                            ->copyable()
                            ->placeholder('Not available'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'successful', 'succeeded' => 'success',
                                'pending', 'processing' => 'warning',
                                'failed', 'abandoned' => 'danger',
                                'refunded' => 'info',
                                default => 'gray',
                            }),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),

                        TextEntry::make('checkout_expires_at')
                            ->label('Application Checkout Timeout')
                            ->dateTime()
                            ->placeholder('Not set'),
                    ])
                    ->columns(2),

                Section::make('Related Investment')
                    ->schema([
                        TextEntry::make('investment.id')
                            ->label('Investment ID')
                            ->placeholder('Not linked to an investment'),

                        TextEntry::make('investment.status')
                            ->label('Investment Status')
                            ->badge()
                            ->placeholder('Not available'),

                        TextEntry::make('investment.units')
                            ->label('Equity Units')
                            ->numeric(decimalPlaces: 6)
                            ->placeholder('Not available'),
                    ])
                    ->columns(2),

                Section::make('Provider Response')
                    ->schema([
                        TextEntry::make('payload')
                            ->label('Stored Provider Data')
                            ->formatStateUsing(fn ($state) =>
                                is_array($state)
                                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                    : (is_string($state) ? $state : json_encode($state))
                            )
                            ->placeholder('No provider response stored')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.first_name')
                    ->label('Investor')
                    ->formatStateUsing(fn ($state, $record) =>
                        trim(($record->user?->first_name ?? '') . ' ' .
                            ($record->user?->last_name ?? ''))
                    )
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('currency')
                    ->badge()
                    ->sortable(),

                TextColumn::make('provider')
                    ->label('Payment Provider')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider_reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'successful', 'succeeded' => 'success',
                        'pending', 'processing' => 'warning',
                        'failed', 'abandoned' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date Created')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'successful' => 'Successful',
                        'succeeded' => 'Succeeded',
                        'failed' => 'Failed',
                        'abandoned' => 'Abandoned',
                        'refunded' => 'Refunded',
                    ]),

                SelectFilter::make('provider')
                    ->options([
                        'paystack' => 'Paystack',
                        'manual' => 'Manual',
                        'fadapay' => 'FadaPay',
                        'korapay' => 'Korapay',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
