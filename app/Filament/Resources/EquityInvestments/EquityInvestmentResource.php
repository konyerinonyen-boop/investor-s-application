<?php

namespace App\Filament\Resources\EquityInvestments;

use App\Filament\Resources\EquityInvestments\Pages\ManageEquityInvestments;
use App\Filament\Support\AdminResource;
use App\Models\EquityInvestment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquityInvestmentResource extends AdminResource
{
    protected static ?string $model = EquityInvestment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Investment Operations';

    protected static ?string $permissionResource = 'equity investments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('investor_id')->relationship('investor', 'name')->searchable()->preload()->required(),
                Select::make('equity_round_id')->relationship('round', 'name')->searchable()->preload()->required(),
                TextInput::make('amount')->numeric()->minValue(0)->required(),
                TextInput::make('units')->numeric()->minValue(0)->required(),
                Select::make('status')->options(['pending' => 'Pending', 'committed' => 'Committed', 'funded' => 'Funded', 'cancelled' => 'Cancelled'])->required(),
                TextInput::make('payment_reference')->maxLength(255),
                TextInput::make('agreement_id')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('investor.name')->searchable()->sortable(),
                TextColumn::make('round.name')->label('Round')->searchable()->sortable(),
                TextColumn::make('amount')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('units')->numeric(decimalPlaces: 2),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'committed' => 'Committed', 'funded' => 'Funded', 'cancelled' => 'Cancelled']),
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
            'index' => ManageEquityInvestments::route('/'),
        ];
    }
}
