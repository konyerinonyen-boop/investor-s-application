<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $resources = [
            'users',
            'products',
            'equity rounds',
            'equity investments',
            'loan offers',
            'loans',
            'KYC profiles',
            'documents',
            'payments',
            'repayment schedules',
            'notifications',
        ];

        $permissions = ['access admin panel', 'manage user roles', 'view roles', 'manage roles'];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$action} {$resource}";
            }
        }

        $permissions[] = 'view activity logs';
        $permissions = array_values(array_unique($permissions));

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('super_admin', 'web')->syncPermissions($permissions);
        Role::findOrCreate('admin', 'web')->syncPermissions(array_values(array_diff($permissions, ['manage roles'])));

        $operationsResources = [
            'products',
            'equity rounds',
            'equity investments',
            'loan offers',
            'loans',
            'KYC profiles',
            'documents',
            'payments',
            'repayment schedules',
        ];

        Role::findOrCreate('operations', 'web')->syncPermissions([
            'access admin panel',
            ...$this->permissionsFor($operationsResources),
        ]);

        Role::findOrCreate('support', 'web')->syncPermissions([
            'access admin panel',
            'view users',
            'view KYC profiles',
            'view documents',
            'view payments',
            'view notifications',
            'view activity logs',
        ]);

        Role::findOrCreate('auditor', 'web')->syncPermissions([
            'access admin panel',
            'view roles',
            'view activity logs',
            ...$this->permissionsFor($resources, ['view']),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string>  $resources
     * @param  array<string>  $actions
     * @return array<string>
     */
    private function permissionsFor(array $resources, array $actions = ['view', 'create', 'update', 'delete']): array
    {
        $permissions = [];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $permissions[] = "{$action} {$resource}";
            }
        }

        return $permissions;
    }
}
