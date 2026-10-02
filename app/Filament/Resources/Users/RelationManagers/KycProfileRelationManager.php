<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\AdminResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KycProfileRelationManager extends RelationManager
{
    protected static string $relationship = 'kycProfile';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('status')->badge(),
                TextColumn::make('review_notes')->wrap()->limit(120),
                TextColumn::make('updated_at')->label('Last reviewed')->dateTime()->since(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AdminResource::userHasPermission('view KYC profiles');
    }
}
