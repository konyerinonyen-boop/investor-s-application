<?php

namespace App\Filament\Support;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

abstract class AdminResource extends Resource
{
    protected static ?string $permissionResource = null;

    public static function shieldPermissionName(string $affix): ?string
    {
        if (static::$permissionResource === null) {
            return null;
        }

        $action = match ($affix) {
            'viewAny', 'view' => 'view',
            'create' => 'create',
            'update' => 'update',
            'delete', 'deleteAny' => 'delete',
            default => null,
        };

        return $action === null
            ? null
            : "{$action} ".static::$permissionResource;
    }

    public static function canViewAny(): bool
    {
        return static::hasPermission('view');
    }

    public static function canView(Model $record): bool
    {
        return static::hasPermission('view');
    }

    public static function canCreate(): bool
    {
        return static::hasPermission('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasPermission('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasPermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::hasPermission('delete');
    }

    protected static function hasPermission(string $action): bool
    {
        $permission = static::shieldPermissionName(match ($action) {
            'view' => 'viewAny',
            default => $action,
        });

        return $permission !== null && static::userHasPermission($permission);
    }

    public static function userHasPermission(string $permission): bool
    {
        $user = Auth::user();

        return $user !== null && Gate::forUser($user)->check($permission);
    }
}
