<?php

namespace App\Filament\Resources\LoanOffers;

use App\Filament\Resources\LoanOffers\Pages\ManageLoanOffers;
use App\Filament\Support\AdminResource;
use App\Models\LoanOffer;
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

class LoanOfferResource extends AdminResource
{
    protected static ?string $model = LoanOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Opportunities';

    protected static ?string $permissionResource = 'loan offers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->relationship('product', 'name')->searchable()->preload(),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('status')->options(['draft' => 'Draft', 'open' => 'Open', 'paused' => 'Paused', 'closed' => 'Closed'])->required(),
                TextInput::make('interest_rate')->numeric()->minValue(0)->suffix('%')->required(),
                TextInput::make('term_months')->integer()->minValue(1)->required(),
                TextInput::make('minimum_amount')->numeric()->minValue(0)->required(),
                TextInput::make('maximum_amount')->numeric()->minValue(0),
                DateTimePicker::make('opened_at'),
                DateTimePicker::make('closed_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('product.name')->label('Product')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('interest_rate')->suffix('%')->sortable(),
                TextColumn::make('term_months')->suffix(' months'),
                TextColumn::make('minimum_amount')->numeric(decimalPlaces: 2),
                TextColumn::make('maximum_amount')->numeric(decimalPlaces: 2)->placeholder('No limit'),
                TextColumn::make('loans_count')->counts('loans')->label('Loans'),
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
            'index' => ManageLoanOffers::route('/'),
        ];
    }
}
