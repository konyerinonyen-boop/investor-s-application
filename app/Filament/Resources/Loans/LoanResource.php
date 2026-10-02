<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Pages\ManageLoans;
use App\Filament\Support\AdminResource;
use App\Models\Loan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoanResource extends AdminResource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Investment Operations';

    protected static ?string $permissionResource = 'loans';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('loan_offer_id')->relationship('offer', 'name')->searchable()->preload()->required(),
                Select::make('investor_id')->relationship('investor', 'name')->searchable()->preload()->required(),
                TextInput::make('principal_amount')->numeric()->minValue(0)->required(),
                TextInput::make('interest_rate')->numeric()->minValue(0)->suffix('%')->required(),
                TextInput::make('term_months')->integer()->minValue(1)->required(),
                Select::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'funded' => 'Funded', 'active' => 'Active', 'repaid' => 'Repaid', 'defaulted' => 'Defaulted', 'rejected' => 'Rejected'])->required(),
                DatePicker::make('funded_at'),
                DatePicker::make('maturity_date'),
                TextInput::make('agreement_id')->maxLength(255),
                TextInput::make('payment_reference')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('investor.name')->searchable()->sortable(),
                TextColumn::make('offer.name')->label('Offer')->searchable()->sortable(),
                TextColumn::make('principal_amount')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('interest_rate')->suffix('%'),
                TextColumn::make('status')->badge(),
                TextColumn::make('maturity_date')->date()->sortable(),
                TextColumn::make('schedules_count')->counts('schedules')->label('Installments'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'funded' => 'Funded', 'active' => 'Active', 'repaid' => 'Repaid', 'defaulted' => 'Defaulted', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
        ];
    }
}
