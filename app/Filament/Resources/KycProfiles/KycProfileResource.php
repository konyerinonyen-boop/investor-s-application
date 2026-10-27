<?php

namespace App\Filament\Resources\KycProfiles;

use App\Filament\Resources\KycProfiles\Pages\ManageKycProfiles;
use App\Filament\Resources\KycProfiles\Pages\ViewKycProfile;
use App\Filament\Support\AdminResource;
use App\Models\KycProfile;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KycProfileResource extends AdminResource
{
    protected static ?string $model = KycProfile::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup =
        'Risk & Compliance';

    protected static ?string $permissionResource = 'KYC profiles';

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
            Section::make('Verification Summary')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('status')
                            ->label('Overall KYC Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'verified' => 'success',
                                'rejected' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M d, Y h:i A'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y h:i A'),
                    ]),
                ]),

            Section::make('Applicant Information')
                ->icon(Heroicon::OutlinedUser)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('full_name')
                            ->label('Full Name')
                            ->state(fn (KycProfile $record): string => trim(
                                ($record->user?->first_name ?? '') . ' ' .
                                ($record->user?->last_name ?? '')
                            )),

                        TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),

                        TextEntry::make('user.phone')
                            ->label('Phone')
                            ->copyable(),

                        TextEntry::make('user.country')
                            ->label('Country'),
                    ]),
                ]),

            Section::make('Sumsub Identity Verification')
                ->icon(Heroicon::OutlinedIdentification)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('sumsub_applicant_id')
                            ->label('Sumsub Applicant ID')
                            ->copyable()
                            ->placeholder('Not yet assigned'),

                        TextEntry::make('identity_verification_status')
                            ->label('Identity Result')
                            ->badge()
                            ->placeholder('Awaiting result')
                            ->color(fn (?string $state): string => match ($state) {
                                'verified' => 'success',
                                'rejected' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('identity_verified_at')
                            ->label('Identity Verified At')
                            ->dateTime('M d, Y h:i A')
                            ->placeholder('Not verified'),
                    ]),

                    TextEntry::make('rejection_reason')
                        ->label('Sumsub Rejection Reason')
                        ->placeholder('No rejection reason returned')
                        ->columnSpanFull(),
                ]),

            Section::make('Paystack Bank Verification')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('bank_name')
                            ->label('Bank'),

                        TextEntry::make('bank_code')
                            ->label('Bank Code'),

                        TextEntry::make('account_number')
                            ->label('Account Number')
                            ->copyable(),

                        TextEntry::make('account_name')
                            ->label('Account Name Returned by Paystack')
                            ->placeholder('Not yet returned'),

                        TextEntry::make('bank_verification_status')
                            ->label('Bank Verification Result')
                            ->badge()
                            ->placeholder('Awaiting result')
                            ->color(fn (?string $state): string => match ($state) {
                                'verified', 'approved' => 'success',
                                'rejected', 'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('bank_verification_reference')
                            ->label('Paystack Reference')
                            ->copyable()
                            ->placeholder('Not available'),

                        TextEntry::make('bank_verified_at')
                            ->label('Bank Verified At')
                            ->dateTime('M d, Y h:i A')
                            ->placeholder('Not verified'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Applicant')
                    ->state(fn (KycProfile $record): string => trim(
                        ($record->user?->first_name ?? '') . ' ' .
                        ($record->user?->last_name ?? '')
                    ))
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where(function ($nameQuery) use ($search) {
                                $nameQuery
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->weight('bold'),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(),

                TextColumn::make('identity_verification_status')
                    ->label('Sumsub')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('bank_verification_status')
                    ->label('Paystack')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'verified', 'approved' => 'success',
                        'rejected', 'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Overall KYC')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->recordUrl(
                fn (KycProfile $record): string => static::getUrl(
                    'view',
                    ['record' => $record]
                )
            )
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKycProfiles::route('/'),
            'view' => ViewKycProfile::route('/{record}'),
        ];
    }
}
