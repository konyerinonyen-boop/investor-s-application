<?php

namespace App\Filament\Resources\KycProfiles;

use App\Filament\Resources\KycProfiles\Pages\ManageKycProfiles;
use App\Filament\Resources\KycProfiles\Pages\ViewKycProfile;
use App\Filament\Support\AdminResource;
use App\Models\KycProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Filament\Infolists\Components\ViewEntry;

class KycProfileResource extends AdminResource
{
    protected static ?string $model = KycProfile::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup =
        'Risk & Compliance';

    protected static ?string $permissionResource = 'KYC profiles';

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    public static function canCreate(): bool
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

    /*
    |--------------------------------------------------------------------------
    | KYC Details / Review View
    |--------------------------------------------------------------------------
    */

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | KYC STATUS
                |--------------------------------------------------------------------------
                */

                Section::make('KYC Review')
                    ->description(
                        'Review the applicant information and submitted documents before making a decision.'
                    )
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Grid::make(3)
                            ->schema([

                                TextEntry::make('status')
                                    ->label('Current Status')
                                    ->badge()
                                    ->color(
                                        fn (?string $state): string => match ($state) {
                                            'pending' => 'warning',
                                            'verified' => 'success',
                                            'rejected' => 'danger',
                                            default => 'gray',
                                        }
                                    )
                                    ->formatStateUsing(
                                        fn (?string $state): string => str($state ?? 'unknown')
                                            ->replace('_', ' ')
                                            ->title()
                                    ),

                                TextEntry::make('created_at')
                                    ->label('Submitted')
                                    ->dateTime('M d, Y h:i A'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('M d, Y h:i A'),
                            ]),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | APPLICANT INFORMATION
                |--------------------------------------------------------------------------
                */

                Section::make('Applicant Information')
                    ->description(
                        'Personal information associated with this KYC application.'
                    )
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        Grid::make(2)
                            ->schema([

                            TextEntry::make('full_name')
                                ->label('Full Name')
                                ->weight('bold')
                                ->state(fn (KycProfile $record): string => trim(
                                    ($record->user?->first_name ?? '') . ' ' .
                                    ($record->user?->last_name ?? '')
                                )),

                                TextEntry::make('user.email')
                                    ->label('Email Address')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable(),

                                TextEntry::make('user.phone')
                                    ->label('Phone Number')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable(),

                                TextEntry::make('user.country')
                                    ->label('Country'),

                            ]),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | IDENTITY DOCUMENT
                |--------------------------------------------------------------------------
                */

                Section::make('Identity Verification')
                    ->description(
                        'Government-issued identification submitted by the applicant.'
                    )
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([

                        Grid::make(2)
                            ->schema([

                                TextEntry::make('id_document_type')
                                    ->label('Document Type')
                                    ->formatStateUsing(
                                        fn (?string $state): string => str($state ?? '')
                                            ->replace('_', ' ')
                                            ->title()
                                    ),

                                TextEntry::make('id_document_number')
                                    ->label('Document Number')
                                    ->copyable()
                                    ->copyMessage('Document number copied'),

                            ]),

                        Grid::make(2)
                            ->schema([

                  ViewEntry::make('id_document_path_front')
                        ->label('Front of ID')
                        ->view('filament.kyc.image'),

                    ViewEntry::make('id_document_path_back')
                    ->label('Back of ID')
                    ->view('filament.kyc.image'),

                            ]),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | SELFIE
                |--------------------------------------------------------------------------
                */

                Section::make('Selfie Verification')
                    ->description(
                        'Selfie submitted by the applicant for identity verification.'
                    )
                    ->icon(Heroicon::OutlinedCamera)
                    ->schema([
                       ViewEntry::make('selfie_path')
                        ->label('Applicant Selfie')
                        ->view('filament.kyc.image'),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | BANK INFORMATION
                |--------------------------------------------------------------------------
                */

                Section::make('Bank Information')
                    ->description(
                        'Bank account information submitted for the applicant.'
                    )
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->schema([

                        Grid::make(2)
                            ->schema([

                                TextEntry::make('bank_name')
                                    ->label('Bank'),

                                TextEntry::make('account_number')
                                    ->label('Account Number')
                                    ->copyable()
                                    ->copyMessage('Account number copied'),

                            ]),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | ADMIN REVIEW
                |--------------------------------------------------------------------------
                */

                Section::make('Review Information')
                    ->description(
                        'Internal review information. These values are generated automatically when the KYC is reviewed.'
                    )
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->schema([

                        Grid::make(2)
                            ->schema([

                                TextEntry::make('reviewer_full_name')
                            ->label('Reviewed By')
                            ->state(function (KycProfile $record): ?string {
                                if (! $record->reviewer) {
                                    return null;
                                }

                                return trim(
                                    ($record->reviewer->first_name ?? '') . ' ' .
                                    ($record->reviewer->last_name ?? '')
                                );
                            })
                            ->placeholder('Not reviewed yet'),

                                TextEntry::make('reviewed_at')
                                    ->label('Reviewed At')
                                    ->dateTime('M d, Y h:i A')
                                    ->placeholder('Not reviewed yet'),

                            ]),

                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('No rejection reason')
                            ->columnSpanFull(),

                    ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KYC Table
    |--------------------------------------------------------------------------
    */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                     TextColumn::make('full_name')
                                ->label('Full Name')
                                ->weight('bold')
                                ->state(fn (KycProfile $record): string => trim(
                                    ($record->user?->first_name ?? '') . ' ' .
                                    ($record->user?->last_name ?? '')
                                )),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('id_document_type')
                    ->label('Document')
                    ->formatStateUsing(
                        fn (?string $state): string => str($state ?? '')
                            ->replace('_', ' ')
                            ->title()
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }
                    )
                    ->formatStateUsing(
                        fn (?string $state): string => str($state ?? 'unknown')
                            ->replace('_', ' ')
                            ->title()
                    ),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y')
                    ->sortable(),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime('M d, Y')
                    ->placeholder('Not reviewed')
                    ->sortable(),

            ])

            /*
            |--------------------------------------------------------------------------
            | ROW ACTIONS
            |--------------------------------------------------------------------------
            */

            ->recordActions([

                /*
                |--------------------------------------------------------------------------
                | APPROVE
                |--------------------------------------------------------------------------
                */

                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve KYC Application')
                    ->modalDescription(
                        'Please confirm that you have reviewed the submitted identity documents and applicant information.'
                    )
                    ->modalSubmitActionLabel('Yes, Approve KYC')
                    ->action(function (KycProfile $record): void {

                    DB::transaction(function () use ($record): void {

                        $record->update([
                            'status' => 'verified',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'rejection_reason' => null,
                        ]);

                        $record->user->update([
                            'kyc_status' => 'verified',
                        ]);
                    });
                })
                ->visible(
                    fn (KycProfile $record): bool =>
                        in_array($record->status, [
                            'pending',
                            'rejected',
                        ], true)
                ),

                /*
                |--------------------------------------------------------------------------
                | REJECT
                |--------------------------------------------------------------------------
                */

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->form([

                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->placeholder(
                                'Clearly explain why this KYC application is being rejected...'
                            )
                            ->helperText(
                                'Provide a clear reason so the applicant can understand what needs to be corrected.'
                            )
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(5),

                    ])
                    ->modalHeading('Reject KYC Application')
                    ->modalDescription(
                        'The rejection reason will be stored with the KYC review record.'
                    )
                    ->modalSubmitActionLabel('Reject KYC')
                    ->action(function (
                        KycProfile $record,
                        array $data
                    ): void {

                        DB::transaction(function () use ($record, $data): void {

                            $record->update([
                                'status' => 'rejected',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                                'rejection_reason' => $data['rejection_reason'],
                            ]);
                        });
                    })
                    ->visible(
                        fn (KycProfile $record): bool =>
                            in_array($record->status, [
                                'pending',
                            ], true)
                    ),
            ])

            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => ManageKycProfiles::route('/'),
            'view' => ViewKycProfile::route('/{record}'),
        ];
    }
}