<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Pages\ManageLoans;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Filament\Support\AdminResource;
use App\Models\AuditLog;
use App\Models\Loan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanResource extends AdminResource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon =
        \Filament\Support\Icons\Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup =
        'Investment Operations';

    protected static ?string $navigationLabel = 'Loan Management';

    protected static ?string $modelLabel = 'loan';

    protected static ?string $pluralModelLabel = 'Loans';

    protected static ?string $permissionResource = 'loans';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Loan overview')
                ->description('Application status and key loan details.')
                ->icon('heroicon-o-document-text')
                ->columns(3)
                ->schema([
                    TextEntry::make('id')
                        ->label('Loan reference')
                        ->prefix('#')
                        ->copyable(),

                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (?string $state) => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'info',
                            'funded' => 'primary',
                            'active', 'repaid' => 'success',
                            'defaulted', 'rejected' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(
                            fn (?string $state) => str($state ?? 'unknown')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                        ),

                    TextEntry::make('offer.name')
                        ->label('Loan offer')
                        ->placeholder('No offer assigned'),

                    TextEntry::make('full_name')
                        ->label('Investor')
                        ->weight('bold')
                        ->state(fn (Loan $record): string => trim(
                            ($record->investor?->first_name ?? '') . ' ' .
                            ($record->investor?->last_name ?? '')
                                )),
                // ->description(fn ($record) => $record->investor?->email),

                    TextEntry::make('investor.email')
                        ->label('Investor email')
                        ->placeholder('No email available')
                        ->copyable(),

                    TextEntry::make('created_at')
                        ->label('Application date')
                        ->dateTime()
                        ->placeholder('Not available'),
                ]),

            Section::make('Financial details')
                ->description('Principal and agreed interest terms.')
                ->icon('heroicon-o-banknotes')
                ->columns(2)
                ->schema([
                    TextEntry::make('principal_amount')
                        ->label('Loan principal')
                        ->money('NGN')
                        ->weight('bold'),

                    TextEntry::make('interest_rate')
                        ->label('Interest rate')
                        ->suffix('%'),

                    TextEntry::make('term_months')
                        ->label('Loan term')
                        ->suffix(' months'),

                    TextEntry::make('maturity_date')
                        ->label('Maturity date')
                        ->date()
                        ->placeholder('Not set')
                        ->color(fn (Loan $record) =>
                            $record->maturity_date &&
                            $record->maturity_date->isPast() &&
                            $record->status !== 'repaid'
                                ? 'danger'
                                : 'success'
                        ),

                    TextEntry::make('funded_at')
                        ->label('Funding date')
                        ->dateTime()
                        ->placeholder('Awaiting funding'),

                    TextEntry::make('payment_reference')
                        ->label('Funding payment reference')
                        ->placeholder('No reference recorded')
                        ->copyable(),
                ]),

            Section::make('Repayment progress')
                ->description('Overview of the repayment schedule attached to this loan.')
                ->icon('heroicon-o-calendar-days')
                ->columns(3)
                ->schema([
                    TextEntry::make('schedules_count')
                        ->label('Total installments')
                        ->state(fn (Loan $record) =>
                            $record->schedules()->count()
                        )
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('paid_installments')
                        ->label('Paid installments')
                        ->state(fn (Loan $record) =>
                            $record->schedules()
                                ->where('status', 'paid')
                                ->count()
                        )
                        ->badge()
                        ->color('success'),

                    TextEntry::make('unpaid_installments')
                        ->label('Outstanding installments')
                        ->state(fn (Loan $record) =>
                            $record->schedules()
                                ->where('status', '!=', 'paid')
                                ->count()
                        )
                        ->badge()
                        ->color(fn (Loan $record) =>
                            $record->schedules()
                                ->where('status', '!=', 'paid')
                                ->exists()
                                    ? 'warning'
                                    : 'success'
                        ),

                    TextEntry::make('overdue_installments')
                        ->label('Overdue installments')
                        ->state(fn (Loan $record) =>
                            $record->schedules()
                                ->where('status', '!=', 'paid')
                                ->whereDate('due_date', '<', today())
                                ->count()
                        )
                        ->badge()
                        ->color(fn (Loan $record) =>
                            $record->schedules()
                                ->where('status', '!=', 'paid')
                                ->whereDate('due_date', '<', today())
                                ->exists()
                                    ? 'danger'
                                    : 'success'
                        ),

                    TextEntry::make('total_scheduled')
                        ->label('Total scheduled repayment')
                        ->state(fn (Loan $record) =>
                            '₦' . number_format(
                                (float) $record->schedules()
                                    ->sum('amount'),
                                2
                            )
                        )
                        ->weight('bold'),

                    TextEntry::make('total_paid')
                        ->label('Total recorded as paid')
                        ->state(fn (Loan $record) =>
                            '₦' . number_format(
                                (float) $record->schedules()
                                    ->where('status', 'paid')
                                    ->sum('amount'),
                                2
                            )
                        )
                        ->color('success')
                        ->weight('bold'),
                ]),

            Section::make('Record timestamps')
                ->icon('heroicon-o-clock')
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),

                    TextEntry::make('updated_at')
                        ->label('Last updated')
                        ->dateTime(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query->with([
                    'investor',
                    'offer',
                ])->withCount('schedules')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Loan ID')
                    ->prefix('#')
                    ->sortable()
                    ->searchable(),

              TextColumn::make('full_name')
                                ->label('Investor')
                                ->weight('bold')
                                ->state(fn (Loan $record): string => trim(
                                    ($record->investor?->first_name ?? '') . ' ' .
                                    ($record->investor?->last_name ?? '')
                                ))
                ->description(fn ($record) => $record->investor?->email),

                TextColumn::make('offer.name')
                    ->label('Loan Offer')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No offer'),

                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('interest_rate')
                    ->label('Interest')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('term_months')
                    ->label('Term')
                    ->formatStateUsing(
                        fn ($state) => $state . ' months'
                    )
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state) => match ($state) {
                            'pending' => 'Pending review',
                            'approved' => 'Approved',
                            'funded' => 'Awaiting activation',
                            'active' => 'Repayment in progress',
                            'repaid' => 'Fully repaid',
                            'defaulted' => 'Defaulted',
                            'rejected' => 'Rejected',
                            default => str($state ?? 'Unknown')
                                ->replace('_', ' ')
                                ->title()
                                ->toString(),
                        }
                    )
                    ->color(
                        fn (?string $state) => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'info',
                            'funded' => 'primary',
                            'active' => 'success',
                            'repaid' => 'success',
                            'defaulted', 'rejected' => 'danger',
                            default => 'gray',
                        }
                    )
                    ->sortable(),

                TextColumn::make('schedules_count')
                    ->label('Installments')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('overdue_installments')
                    ->label('Overdue')
                    ->state(
                        fn (Loan $record) => $record->schedules()
                            ->where('status', '!=', 'paid')
                            ->whereDate('due_date', '<', today())
                            ->count()
                    )
                    ->badge()
                    ->color(
                        fn ($state) => (int) $state > 0
                            ? 'danger'
                            : 'success'
                    ),

                TextColumn::make('maturity_date')
                    ->label('Maturity')
                    ->date()
                    ->placeholder('Not set')
                    ->color(
                        fn (Loan $record) =>
                            $record->maturity_date &&
                            $record->maturity_date->isPast() &&
                            $record->status !== 'repaid'
                                ? 'danger'
                                : 'gray'
                    )
                    ->sortable(),

                TextColumn::make('funded_at')
                    ->label('Funded on')
                    ->dateTime()
                    ->placeholder('Not funded')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Loan status')
                    ->options([
                        'pending_funding' => 'Pending review',
                        'approved' => 'Approved',
                        'funded' => 'Funded',
                        'active' => 'Active',
                        'repaid' => 'Repaid',
                        'defaulted' => 'Defaulted',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('loan_offer_id')
                    ->label('Loan offer')
                    ->relationship('offer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve loan application')
                    ->modalDescription(
                        'Approval does not mean the loan has been funded. The investor must complete payment.'
                    )
                    ->visible(
                        fn (Loan $record) =>
                            $record->status === 'pending_funding'
                    )
                    ->action(function (Loan $record): void {
                        DB::transaction(function () use ($record): void {
                            $loan = Loan::query()
                                ->lockForUpdate()
                                ->findOrFail($record->id);

                            if ($loan->status !== 'pending_funding') {
                                throw new RuntimeException(
                                    'Only pending loans can be approved.'
                                );
                            }

                            $oldStatus = $loan->status;

                            $loan->update([
                                'status' => 'approved',
                            ]);

                            AuditLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'loan_approved',
                                'model' => Loan::class,
                                'record_id' => $loan->id,
                                'old_values' => [
                                    'status' => $oldStatus,
                                ],
                                'new_values' => [
                                    'status' => 'approved',
                                ],
                                'message' =>
                                    'Loan application approved.',
                            ]);
                        });
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn (Loan $record) =>
                            $record->status === 'pending_funding'
                    )
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject loan application')
                    ->action(function (
                        Loan $record,
                        array $data
                    ): void {
                        DB::transaction(function () use (
                            $record,
                            $data
                        ): void {
                            $loan = Loan::query()
                                ->lockForUpdate()
                                ->findOrFail($record->id);

                            if ($loan->status !== 'pending_funding') {
                                throw new RuntimeException(
                                    'Only pending loans can be rejected.'
                                );
                            }

                            $oldStatus = $loan->status;

                            $loan->update([
                                'status' => 'rejected',
                            ]);

                            AuditLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'loan_rejected',
                                'model' => Loan::class,
                                'record_id' => $loan->id,
                                'old_values' => [
                                    'status' => $oldStatus,
                                ],
                                'new_values' => [
                                    'status' => 'rejected',
                                ],
                                'message' =>
                                    'Rejection reason: ' . $data['reason'],
                            ]);
                        });
                    }),

                Action::make('markRepaid')
                    ->label('Mark fully repaid')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm loan repayment')
                    ->modalDescription(
                        'Use this only after verifying that every scheduled repayment, including interest, has actually been paid to the investor.'
                    )
                    ->visible(
                        fn (Loan $record) =>
                            in_array(
                                $record->status,
                                ['funded', 'active'],
                                true
                            )
                    )
                    ->action(function (Loan $record): void {
                        DB::transaction(function () use ($record): void {
                            $loan = Loan::query()
                                ->lockForUpdate()
                                ->findOrFail($record->id);

                            if (! in_array(
                                $loan->status,
                                ['funded', 'active'],
                                true
                            )) {
                                throw new RuntimeException(
                                    'Only funded or active loans can be marked repaid.'
                                );
                            }

                            $schedules = $loan->schedules();

                            if (! $schedules->exists()) {
                                throw new RuntimeException(
                                    'This loan has no repayment schedule. Create its installments first.'
                                );
                            }

                            $unpaidExists = $loan->schedules()
                                ->where('status', '!=', 'paid')
                                ->exists();

                            if ($unpaidExists) {
                                throw new RuntimeException(
                                    'The loan cannot be marked repaid while installments remain unpaid.'
                                );
                            }

                            $oldStatus = $loan->status;

                            $loan->update([
                                'status' => 'repaid',
                            ]);

                            AuditLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'loan_marked_repaid',
                                'model' => Loan::class,
                                'record_id' => $loan->id,
                                'old_values' => [
                                    'status' => $oldStatus,
                                ],
                                'new_values' => [
                                    'status' => 'repaid',
                                ],
                                'message' =>
                                    'Loan marked fully repaid after all installments were confirmed paid.',
                            ]);
                        });
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoans::route('/'),
            'view' => ViewLoan::route('/{record}'),
        ];
    }
}