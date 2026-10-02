<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AdminResource;
use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentActivity extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminResource::userHasPermission('view activity logs');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest activity')
            ->query(fn (): Builder => AuditLog::query()->with('user')->latest('created_at'))
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Actor')->placeholder('System')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('model')->searchable(),
                TextColumn::make('message')->wrap()->limit(90),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }
}
