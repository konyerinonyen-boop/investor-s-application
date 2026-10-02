<?php

namespace App\Filament\Resources\EquityRounds;

use App\Filament\Resources\EquityRounds\Pages\ManageEquityRounds;
use App\Filament\Support\AdminResource;
use App\Models\EquityRound;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquityRoundResource extends AdminResource
{
    protected static ?string $model = EquityRound::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Opportunities';

    protected static ?string $permissionResource = 'equity rounds';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->relationship('product', 'name')->searchable()->preload(),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('status')->options(['draft' => 'Draft', 'open' => 'Open', 'paused' => 'Paused', 'closed' => 'Closed'])->required(),
                TextInput::make('valuation')->numeric()->minValue(0)->required(),
                TextInput::make('price_per_unit')->numeric()->minValue(0)->required(),
                TextInput::make('total_units')->integer()->minValue(0)->required(),
                TextInput::make('available_units')->integer()->minValue(0)->required(),
                TextInput::make('minimum_ticket')->numeric()->minValue(0)->required(),
                TextInput::make('maximum_ticket')->numeric()->minValue(0),
                DateTimePicker::make('opens_at'),
                DateTimePicker::make('closes_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('product.name')->label('Product')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('valuation')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('available_units')->numeric()->label('Units available'),
                TextColumn::make('closes_at')->dateTime()->sortable(),
                TextColumn::make('investments_count')->counts('investments')->label('Investments'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'open' => 'Open', 'paused' => 'Paused', 'closed' => 'Closed']),
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
            'index' => ManageEquityRounds::route('/'),
        ];
    }
}
