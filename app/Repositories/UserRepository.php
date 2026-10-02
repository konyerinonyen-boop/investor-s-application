<?php

namespace App\Repositories;

use App\Models\User;
use Spatie\Permission\Models\Role;

class UserRepository
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function assignInvestorRole(User $user): void
    {
        $role = Role::firstOrCreate([
            'name' => 'investor',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }
}
