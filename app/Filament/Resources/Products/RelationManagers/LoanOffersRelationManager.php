<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\AdminResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LoanOffersRelationManager extends RelationManager
{
    protected static string $relationship = 'loanOffers';

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
                TextColumn::make('interest_rate')->suffix('%'),
                TextColumn::make('term_months')->suffix(' months'),
                TextColumn::make('loans_count')->counts('loans')->label('Loans'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AdminResource::userHasPermission('view loan offers');
    }
}
