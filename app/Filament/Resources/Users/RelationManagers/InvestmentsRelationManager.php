<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\AdminResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvestmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'investments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('round.name')->label('Round'),
                TextColumn::make('amount')->numeric(decimalPlaces: 2),
                TextColumn::make('units')->numeric(decimalPlaces: 2),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AdminResource::userHasPermission('view equity investments');
    }
}
