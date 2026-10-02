<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_logged_without_sensitive_attributes(): void
    {
        $user = User::factory()->create([
            'name' => 'Audit subject',
            'password' => 'old-secret-password',
        ]);

        $createdLog = AuditLog::query()
            ->where('action', 'record.created')
            ->where('model', User::class)
            ->where('record_id', $user->id)
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $createdLog->new_values);

        $user->update([
            'name' => 'Updated subject',
            'password' => 'new-secret-password',
        ]);

        $updatedLog = AuditLog::query()
            ->where('action', 'record.updated')
            ->where('model', User::class)
            ->where('record_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Audit subject', $updatedLog->old_values['name']);
        $this->assertSame('Updated subject', $updatedLog->new_values['name']);
        $this->assertArrayNotHasKey('password', $updatedLog->old_values);
        $this->assertArrayNotHasKey('password', $updatedLog->new_values);
    }

    public function test_authenticated_api_requests_and_auth_events_are_logged(): void
    {
        $user = User::factory()->create();

        Auth::login($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login',
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me')->assertOk();

        $apiLog = AuditLog::query()->where('action', 'api.request')->firstOrFail();
        $this->assertSame('GET', $apiLog->new_values['method']);
        $this->assertStringEndsWith('v1/me', $apiLog->new_values['endpoint']);
        $this->assertArrayNotHasKey('request_body', $apiLog->new_values);

        Auth::guard('web')->logout();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_role_seeder_grants_admin_access_and_keeps_auditor_read_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminRole = Role::findByName('admin', 'web');
        $auditorRole = Role::findByName('auditor', 'web');

        $this->assertTrue($adminRole->hasPermissionTo('access admin panel'));
        $this->assertFalse($adminRole->hasPermissionTo('manage roles'));
        $this->assertTrue($auditorRole->hasPermissionTo('view users'));
        $this->assertFalse($auditorRole->hasPermissionTo('update users'));
    }

    public function test_artisan_command_grants_super_admin_to_an_existing_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'bootstrap-admin@example.com']);

        $this->artisan('admin:make-super-admin', ['email' => $user->email])
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'roles.super_admin_granted',
            'record_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_super_admin_can_open_user_and_product_relationship_pages(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Admin test product',
            'slug' => 'admin-test-product',
            'instrument_type' => 'equity',
            'status' => 'active',
            'minimum_investment' => 100,
        ]);
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        $this->actingAs($user)
            ->get("/admin/users/{$user->id}")
            ->assertOk();

        $this->get("/admin/products/{$product->id}")
            ->assertOk();
    }
}
