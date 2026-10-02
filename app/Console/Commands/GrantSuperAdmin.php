<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

#[Signature('admin:make-super-admin {email : The email address of an existing user}')]
#[Description('Grant the super-admin role to an existing user')]
class GrantSuperAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $oldRoles = $user->getRoleNames()->all();
        $role = Role::findOrCreate('super_admin', 'web');
        $user->syncRoles($role);

        AuditLog::create([
            'user_id' => null,
            'action' => 'roles.super_admin_granted',
            'model' => User::class,
            'record_id' => $user->id,
            'old_values' => ['roles' => $oldRoles],
            'new_values' => ['roles' => ['super_admin']],
            'message' => "Granted super_admin to {$user->email} through Artisan.",
        ]);

        $this->info("{$user->email} now has the super_admin role.");

        return self::SUCCESS;
    }
}
