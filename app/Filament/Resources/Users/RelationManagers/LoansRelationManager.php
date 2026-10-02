<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\AdminResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('principal_amount')
            ->columns([
                TextColumn::make('offer.name')->label('Offer'),
                TextColumn::make('principal_amount')->numeric(decimalPlaces: 2),
                TextColumn::make('status')->badge(),
                TextColumn::make('maturity_date')->date(),
                TextColumn::make('schedules_count')->counts('schedules')->label('Installments'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AdminResource::userHasPermission('view loans');
    }
}
