<?php

namespace App\Filament\Resources\RepaymentSchedules;

use App\Filament\Resources\RepaymentSchedules\Pages\ManageRepaymentSchedules;
use App\Filament\Support\AdminResource;

use App\Models\RepaymentSchedule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class RepaymentScheduleResource extends AdminResource
{
    protected static ?string $model = RepaymentSchedule::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup =
        'Investment Operations';

    protected static ?string $navigationLabel = 'Repayment Schedules';

    protected static ?string $modelLabel = 'Repayment Schedule';

    protected static ?string $pluralModelLabel = 'Repayment Schedules';

    protected static ?string $permissionResource = 'repayment schedules';

    #[Override]
    public static function canDelete(Model $record): bool
    {
        return false;
    }
    #[Override]
    public static function canCreate(): bool
    {
        return false;
    }
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Loan and investor')
                ->description(
                    'Identify the loan associated with this installment.'
                )
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->columns(2)
                ->schema([
                    TextEntry::make('loan_id')
                        ->label('Loan reference')
                        ->formatStateUsing(
                            fn ($state): string => 'Loan #' . $state
                        )
                        ->weight('bold')
                        ->copyable(),

                    TextEntry::make('investor_name')
                        ->label('Investor')
                        ->state(
                            fn (RepaymentSchedule $record): string => trim(
                                ($record->loan?->investor?->first_name ?? '')
                                . ' ' .
                                ($record->loan?->investor?->last_name ?? '')
                            ) ?: 'Investor unavailable'
                        )
                        ->weight('medium'),

                    TextEntry::make('loan.investor.email')
                        ->label('Investor email')
                        ->placeholder('Not available')
                        ->copyable(),

                    TextEntry::make('loan.principal_amount')
                        ->label('Original loan principal')
                        ->money('NGN')
                        ->placeholder('Not available'),

                    TextEntry::make('loan.interest_rate')
                        ->label('Loan interest rate')
                        ->suffix('%')
                        ->placeholder('Not available'),

                    TextEntry::make('loan.term_months')
                        ->label('Loan term')
                        ->formatStateUsing(
                            fn ($state): string =>
                                $state !== null
                                    ? $state . ' months'
                                    : 'Not available'
                        ),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ]),

            Section::make('Installment overview')
                ->description(
                    'The scheduled payment date, frequency and current state.'
                )
                ->icon(Heroicon::OutlinedCalendarDays)
                ->columns(2)
                ->schema([
                    TextEntry::make('due_date')
                        ->label('Due date')
                        ->date('D, d M Y')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->weight('medium'),

                    TextEntry::make('frequency')
                        ->label('Repayment frequency')
                        ->badge()
                        ->formatStateUsing(
                            fn (?string $state): string =>
                                ucfirst($state ?? 'Unknown')
                        )
                        ->color('gray'),

                    TextEntry::make('status')
                        ->label('Installment status')
                        ->badge()
                        ->formatStateUsing(
                            fn (?string $state): string =>
                                ucfirst(str_replace(
                                    '_',
                                    ' ',
                                    $state ?? 'Unknown'
                                ))
                        )
                        ->color(
                            fn (?string $state): string => match ($state) {
                                'paid' => 'success',
                                'due' => 'info',
                                'pending' => 'gray',
                                'overdue' => 'danger',
                                'cancelled' => 'warning',
                                default => 'gray',
                            }
                        ),

                    TextEntry::make('created_at')
                        ->label('Schedule created')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Not available'),
                ]),

            Section::make('Financial breakdown')
                ->description(
                    'The total installment amount, separated into principal and interest.'
                )
                ->icon(Heroicon::OutlinedBanknotes)
                ->columns(3)
                ->schema([
                    TextEntry::make('amount')
                        ->label('Total amount due')
                        ->money('NGN')
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('principal_amount')
                        ->label('Principal portion')
                        ->money('NGN'),

                    TextEntry::make('interest_amount')
                        ->label('Interest portion')
                        ->money('NGN'),
                ]),

            Section::make('Payment confirmation')
                ->description(
                    'Evidence of the repayment recorded against this installment.'
                )
                ->icon(Heroicon::OutlinedShieldCheck)
                ->columns(2)
                ->schema([
                    TextEntry::make('paid_at')
                        ->label('Payment confirmed at')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Awaiting confirmed payment'),

                    TextEntry::make('payment_reference')
                        ->label('Repayment reference')
                        ->placeholder('No payment reference recorded')
                        ->copyable()
                        ->icon(Heroicon::OutlinedClipboardDocument),
                ]),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | REPAYMENT SCHEDULE TABLE
    |--------------------------------------------------------------------------
    |
    | Groups installments under their loan and orders them by due date.
    |
    */

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with('loan.investor')
                    ->orderBy('loan_id')
                    ->orderBy('due_date')
            )

            ->groups([
                Group::make('loan_id')
                    ->label('Loan')
                    ->collapsible(),
            ])

            ->defaultGroup('loan_id')

            ->columns([
                TextColumn::make('loan_id')
                    ->label('Loan')
                    ->formatStateUsing(
                        fn ($state): string => 'Loan #' . $state
                    )
                    ->sortable()
                    ->searchable(),

                TextColumn::make('investor_name')
                    ->label('Investor')
                    ->state(
                        fn (RepaymentSchedule $record): string => trim(
                            ($record->loan?->investor?->first_name ?? '')
                            . ' ' .
                            ($record->loan?->investor?->last_name ?? '')
                        ) ?: 'Investor unavailable'
                    )
                    ->description(
                        fn (RepaymentSchedule $record): ?string =>
                            $record->loan?->investor?->email
                    )
                    ->weight('medium'),

                TextColumn::make('due_date')
                    ->label('Due date')
                    ->date('d M Y')
                    ->sortable()
                    ->color(
                        fn (RepaymentSchedule $record): string =>
                            ! in_array(
                                $record->status,
                                ['paid', 'cancelled'],
                                true
                            )
                            && $record->due_date
                            && $record->due_date->lt(today())
                                ? 'danger'
                                : 'gray'
                    ),

                TextColumn::make('amount')
                    ->label('Total due')
                    ->money('NGN')
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('NGN')
                    ->toggleable(),

                TextColumn::make('interest_amount')
                    ->label('Interest')
                    ->money('NGN')
                    ->toggleable(),

                TextColumn::make('frequency')
                    ->label('Frequency')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst($state ?? 'Unknown')
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst(str_replace(
                                '_',
                                ' ',
                                $state ?? 'Unknown'
                            ))
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'paid' => 'success',
                            'due' => 'info',
                            'pending' => 'gray',
                            'overdue' => 'danger',
                            'cancelled' => 'warning',
                            default => 'gray',
                        }
                    )
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid at')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Not paid')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('payment_reference')
                    ->label('Payment reference')
                    ->copyable()
                    ->placeholder('Not recorded')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                SelectFilter::make('status')
                    ->label('Installment status')
                    ->options([
                        'pending' => 'Pending',
                        'due' => 'Due',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('frequency')
                    ->label('Repayment frequency')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                    ]),

                Filter::make('overdue_only')
                    ->label('Overdue installments')
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->whereDate('due_date', '<', today())
                            ->whereNotIn(
                                'status',
                                ['paid', 'cancelled']
                            )
                    ),
            ])

            ->recordActions([
                ViewAction::make()
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Repayment installment details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                EditAction::make(),

                DeleteAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            ->striped();
    }


    /*
    |--------------------------------------------------------------------------
    | RESOURCE PAGES
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => ManageRepaymentSchedules::route('/'),
            // 'view' => ::route('/{record}'),
        ];
    }
}
