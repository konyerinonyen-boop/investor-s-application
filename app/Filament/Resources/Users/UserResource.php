<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\InvestmentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\KycProfileRelationManager;
use App\Filament\Resources\Users\RelationManagers\LoansRelationManager;
use App\Filament\Resources\Users\RelationManagers\NotificationsRelationManager;
use App\Filament\Support\AdminResource;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserResource extends AdminResource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'People & Access';

    protected static ?string $permissionResource = 'users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(40),
                        TextInput::make('country')->maxLength(100),
                        Select::make('status')
                            ->options(['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'])
                            ->required(),
                        Select::make('kyc_status')
                            ->options(['not_started' => 'Not started', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                            ->required(),
                        Toggle::make('is_verified')->label('Verified account'),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->minLength(12)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('roles.name')->badge()->separator(','),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('kyc_status')->badge(),
                IconColumn::make('is_verified')->boolean()->label('Verified'),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended']),
                SelectFilter::make('kyc_status')->options(['not_started' => 'Not started', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                Action::make('assignRoles')
                    ->label('Manage roles')
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        Select::make('roles')
                            ->multiple()
                            ->options(fn (): array => Role::query()->where('name', '!=', 'super_admin')->orderBy('name')->pluck('name', 'name')->all())
                            ->default(fn (User $record): array => $record->getRoleNames()->reject(fn (string $roleName): bool => $roleName === 'super_admin')->values()->all())
                            ->required(),
                    ])
                    ->action(function (array $data, User $record): void {
                        $oldRoles = $record->getRoleNames()->all();
                        $record->syncRoles($data['roles']);

                        AuditLog::create([
                            'user_id' => Auth::id(),
                            'action' => 'roles.updated',
                            'model' => User::class,
                            'record_id' => $record->id,
                            'old_values' => ['roles' => $oldRoles],
                            'new_values' => ['roles' => $data['roles']],
                            'message' => "Updated roles for {$record->name}.",
                        ]);
                    })
                    ->visible(fn (User $record): bool => static::userHasPermission('manage user roles') && ! $record->hasRole('super_admin')),
                ViewAction::make()->url(fn (User $record): string => static::getUrl('view', ['record' => $record])),
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
            'index' => ManageUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            KycProfileRelationManager::class,
            InvestmentsRelationManager::class,
            LoansRelationManager::class,
            DocumentsRelationManager::class,
            NotificationsRelationManager::class,
        ];
    }
}
