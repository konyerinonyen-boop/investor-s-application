<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_admin_permission_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_with_admin_permission_can_access_the_panel(): void
    {
        $permission = Permission::create([
            'name' => 'access admin panel',
            'guard_name' => 'web',
        ]);
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}
