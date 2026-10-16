<?php

namespace App\Filament\Resources\RepaymentPayouts;

use App\Filament\Resources\RepaymentPayouts\Pages\CreateRepaymentPayout;
use App\Filament\Resources\RepaymentPayouts\Pages\EditRepaymentPayout;
use App\Filament\Resources\RepaymentPayouts\Pages\ListRepaymentPayouts;
use App\Filament\Resources\RepaymentPayouts\Schemas\RepaymentPayoutForm;
use App\Filament\Resources\RepaymentPayouts\Tables\RepaymentPayoutsTable;
use App\Models\RepaymentPayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RepaymentPayoutResource extends Resource
{
    protected static ?string $model = RepaymentPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'RepaymentPayout';

    public static function form(Schema $schema): Schema
    {
        return RepaymentPayoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepaymentPayoutsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepaymentPayouts::route('/'),
            'create' => CreateRepaymentPayout::route('/create'),
            'edit' => EditRepaymentPayout::route('/{record}/edit'),
        ];
    }
}
