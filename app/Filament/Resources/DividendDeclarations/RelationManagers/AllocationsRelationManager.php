<?php

namespace App\Filament\Resources\DividendDeclarations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';
    protected static ?string $title = 'Investor Allocations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('investor_name')
                    ->label('Investor')
                    ->state(fn ($record): string => trim(($record->investor?->first_name ?? '') . ' ' . ($record->investor?->last_name ?? '')))
                    ->description(fn ($record): ?string => $record->investor?->email)
                    ->searchable(query: fn ($query, string $search) => $query->whereHas('investor', fn ($investorQuery) => $investorQuery->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))),
                TextColumn::make('eligible_units')->label('Eligible Units')->numeric(decimalPlaces: 6)->sortable(),
                TextColumn::make('amount')->label('Dividend')->money('NGN')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'paid' => 'success',
                    'failed' => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('payment_reference')->label('Wallet Reference')->copyable()->toggleable(),
                TextColumn::make('paid_at')->label('Paid At')->dateTime('M j, Y H:i')->sortable()->placeholder('Not paid'),
                TextColumn::make('failure_reason')->label('Failure')->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('eligible_units', 'desc');
    }
}
