<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\EquityRoundsRelationManager;
use App\Filament\Resources\Products\RelationManagers\LoanOffersRelationManager;
use App\Filament\Support\AdminResource;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends AdminResource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Opportunities';

    protected static ?string $permissionResource = 'products';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),
                Textarea::make('description')->rows(4)->columnSpanFull(),
                Select::make('instrument_type')->options(['equity' => 'Equity', 'loan' => 'Loan'])->required(),
                Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed'])->required(),
                TextInput::make('minimum_investment')->numeric()->minValue(0)->required(),
                TextInput::make('maximum_investment')->numeric()->minValue(0),
                TextInput::make('interest_rate')->numeric()->minValue(0)->suffix('%'),
                DateTimePicker::make('launch_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('instrument_type')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('minimum_investment')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('maximum_investment')->numeric(decimalPlaces: 2)->placeholder('No limit'),
                TextColumn::make('launch_date')->date()->sortable(),
                TextColumn::make('equity_rounds_count')->counts('equityRounds')->label('Rounds'),
            ])
            ->filters([
                SelectFilter::make('instrument_type')->options(['equity' => 'Equity', 'loan' => 'Loan']),
                SelectFilter::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed']),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (Product $record): string => static::getUrl('view', ['record' => $record])),
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
            'index' => ManageProducts::route('/'),
            'view' => ViewProduct::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            EquityRoundsRelationManager::class,
            LoanOffersRelationManager::class,
        ];
    }
}
