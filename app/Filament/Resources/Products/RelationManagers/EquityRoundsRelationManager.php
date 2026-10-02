<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\AdminResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EquityRoundsRelationManager extends RelationManager
{
    protected static string $relationship = 'equityRounds';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('valuation')->numeric(decimalPlaces: 2),
                TextColumn::make('available_units')->numeric()->label('Units available'),
                TextColumn::make('closes_at')->dateTime(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AdminResource::userHasPermission('view equity rounds');
    }
}
