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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RepaymentScheduleResource extends AdminResource
{
    protected static ?string $model = RepaymentSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Investment Operations';

    protected static ?string $permissionResource = 'repayment schedules';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('loan_id')->relationship('loan', 'id')->searchable()->preload()->required(),
                DatePicker::make('due_date')->required(),
                TextInput::make('amount')->numeric()->minValue(0)->required(),
                TextInput::make('principal_amount')->numeric()->minValue(0)->required(),
                TextInput::make('interest_amount')->numeric()->minValue(0)->required(),
                Select::make('frequency')->options(['monthly' => 'Monthly', 'quarterly' => 'Quarterly'])->required(),
                Select::make('status')->options(['pending' => 'Pending', 'due' => 'Due', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'])->required(),
                DatePicker::make('paid_at'),
                TextInput::make('payment_reference')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loan_id')->label('Loan')->sortable(),
                TextColumn::make('due_date')->date()->sortable(),
                TextColumn::make('amount')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('frequency')->badge(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'due' => 'Due', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled']),
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
            'index' => ManageRepaymentSchedules::route('/'),
        ];
    }
}
