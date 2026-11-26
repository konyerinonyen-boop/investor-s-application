<?php

namespace App\Filament\Resources\DividendDeclarations;

use App\Filament\Resources\DividendDeclarations\Pages\ManageDividendDeclarations;
use App\Filament\Resources\DividendDeclarations\Pages\ViewDividendDeclaration;
use App\Filament\Resources\DividendDeclarations\RelationManagers\AllocationsRelationManager;
use App\Filament\Support\AdminResource;
use App\Models\DividendDeclaration;
use App\Services\DividendService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

class DividendDeclarationResource extends AdminResource
{
    protected static ?string $model = DividendDeclaration::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static string|\UnitEnum|null $navigationGroup = 'Investment Operations';
    protected static ?string $navigationLabel = 'Dividends';
    protected static ?string $permissionResource = 'dividend declarations';

    #[Override]
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    #[Override]
    public static function canDeleteAny(): bool
    {
        return false;
    }

    #[Override]
    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dividend Summary')->columns(2)->schema([
                TextEntry::make('title')->label('Distribution'),
                TextEntry::make('round.name')->label('Equity Round'),
                TextEntry::make('total_amount')->label('Declared Pool')->money('NGN'),
                TextEntry::make('total_allocated')->label('Allocated')->money('NGN'),
                TextEntry::make('record_date')->date(),
                TextEntry::make('status')->badge(),
                TextEntry::make('declared_at')->dateTime()->placeholder('Not declared'),
                TextEntry::make('approved_at')->dateTime()->placeholder('Not approved'),
                TextEntry::make('paid_at')->dateTime()->placeholder('Not fully paid'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [AllocationsRelationManager::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dividend Declaration')
                ->description('Create a draft distribution for one equity round. The declared amount is the total pool to distribute.')
                ->columns(2)
                ->schema([
                    Select::make('equity_round_id')
                        ->label('Equity Round')
                        ->relationship('round', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('title')
                        ->label('Distribution Name')
                        ->placeholder('e.g. 2026 Annual Dividend')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('total_amount')
                        ->label('Total Dividend Pool')
                        ->prefix('₦')
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->required(),
                    DatePicker::make('record_date')
                        ->label('Record Date')
                        ->required()
                        ->native(false)
                        ->helperText('Investors who held units by the end of this date are eligible.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Dividend')->searchable()->sortable()->weight('bold'),
                TextColumn::make('round.name')->label('Equity Round')->searchable()->sortable(),
                TextColumn::make('total_amount')->label('Declared Pool')->money('NGN')->sortable(),
                TextColumn::make('total_allocated')->label('Allocated')->money('NGN')->sortable(),
                TextColumn::make('record_date')->date('M j, Y')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'approved', 'processing' => 'info',
                        'declared', 'allocations_calculated' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'declared' => 'Declared',
                    'allocations_calculated' => 'Allocations Calculated',
                    'approved' => 'Approved',
                    'processing' => 'Processing',
                    'paid' => 'Paid',
                    'cancelled' => 'Cancelled',
                ]),
                SelectFilter::make('equity_round_id')->label('Equity Round')->relationship('round', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (DividendDeclaration $record): bool => $record->status === 'draft'),
                Action::make('declare')
                    ->label('Declare')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This formally declares the dividend. The record date and amount must be correct.')
                    ->visible(fn (DividendDeclaration $record): bool => $record->status === 'draft' && static::userHasPermission('declare dividends'))
                    ->action(fn (DividendDeclaration $record) => app(DividendService::class)->declare($record, auth()->user())),
                Action::make('calculate')
                    ->label('Calculate Allocations')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('The platform will snapshot eligible units from the ownership ledger. Calculation stops if current holdings and the ledger do not reconcile.')
                    ->visible(fn (DividendDeclaration $record): bool => $record->status === 'declared' && static::userHasPermission('calculate dividend allocations'))
                    ->action(fn (DividendDeclaration $record) => app(DividendService::class)->calculateAllocations($record)),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Approval locks the allocation list. The declarer cannot approve their own declaration.')
                    ->visible(fn (DividendDeclaration $record): bool => $record->status === 'allocations_calculated' && (int) $record->declared_by !== (int) auth()->id() && static::userHasPermission('approve dividends'))
                    ->action(fn (DividendDeclaration $record) => app(DividendService::class)->approve($record, auth()->user())),
                Action::make('pay')
                    ->label('Pay Wallets')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This credits approved investor allocations to their NGN wallets. Each allocation uses an idempotent transaction reference.')
                    ->visible(fn (DividendDeclaration $record): bool => in_array($record->status, ['approved', 'processing'], true) && static::userHasPermission('pay dividends'))
                    ->action(fn (DividendDeclaration $record) => app(DividendService::class)->payApprovedDeclaration($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No dividend declarations')
            ->emptyStateDescription('Create a draft dividend distribution for an equity round to begin the declaration workflow.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDividendDeclarations::route('/'),
            'view' => ViewDividendDeclaration::route('/{record}'),
        ];
    }
}
