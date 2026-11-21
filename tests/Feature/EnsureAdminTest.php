<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Enums\Module;
use Iquesters\Foundation\Http\Middleware\EnsureAdmin;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Support\EntityPermission;
use Iquesters\Foundation\Tests\TestCase;
use Spatie\Permission\Models\Role;

class EnsureAdminTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'ensure.admin'])->get('/_admin-only', fn () => 'ok');
    }

    public function test_guest_is_denied(): void
    {
        $this->get('/_admin-only')->assertForbidden();
    }

    public function test_user_without_admin_role_is_denied(): void
    {
        $this->actingAs($this->makeUser('user'))->get('/_admin-only')->assertForbidden();
        $this->actingAs($this->makeUser())->get('/_admin-only')->assertForbidden();
    }

    public function test_only_platform_admin_is_allowed(): void
    {
        $this->actingAs($this->makeUser('super-admin'))->get('/_admin-only')->assertOk();
        $this->actingAs($this->makeUser('admin'))->get('/_admin-only')->assertForbidden();
    }

    public function test_role_named_web_and_non_web_guard_super_admin_are_not_platform(): void
    {
        // Spatie v8 hasAnyRole() has no guard parameter; 'web' as its
        // second argument would be interpreted as another allowed role.
        $namedWeb = $this->makeUser('web');
        $this->actingAs($namedWeb)->get('/_admin-only')->assertForbidden();
        $this->assertFalse(EntityPermission::allows($namedWeb, 'notices', 'view'));

        $apiOnly = $this->makeUser();
        $apiRole = Role::findOrCreate('super-admin', 'api');
        DB::table('model_has_roles')->insert([
            'role_id' => $apiRole->id,
            'model_type' => $apiOnly->getMorphClass(),
            'model_id' => $apiOnly->getKey(),
        ]);
        $this->actingAs($apiOnly)->get('/_admin-only')->assertForbidden();
        $this->assertFalse(EntityPermission::allows($apiOnly, 'notices', 'view'));
    }

    public function test_configuration_cannot_promote_platform_administrators(): void
    {
        $this->setAdminRoles('owner');

        $this->actingAs($this->makeUser('owner'))->get('/_admin-only')->assertForbidden();
        $this->actingAs($this->makeUser('admin'))->get('/_admin-only')->assertForbidden();
    }

    public function test_super_admin_is_always_allowed(): void
    {
        $this->setAdminRoles('owner');
        $this->actingAs($this->makeUser('super-admin'))->get('/_admin-only')->assertOk();

        $this->setAdminRoles(' , ');
        $this->assertSame(['super-admin'], EnsureAdmin::roles());
    }

    /** Set the DB-backed conf value for this request (ConfProvider reuses the instance). */
    private function setAdminRoles(string $roles): void
    {
        ConfProvider::from(Module::FOUNDATION)->admin_roles = $roles;
    }

    public function test_user_model_without_roles_fails_closed(): void
    {
        $user = new class extends User
        {
            protected $table = 'users';

            protected $guarded = [];
        };
        $user = $user->newQuery()->create(['name' => 'x', 'email' => 'noroles@example.test', 'password' => 'x', 'status' => 'active']);

        $this->actingAs($user)->get('/_admin-only')->assertForbidden();
    }

    public function test_entity_grant_cannot_bypass_platform_or_tenant_policy(): void
    {
        $user = $this->makeUser('admin');
        foreach (['users', 'roles', 'integrations', 'channels', 'organisation_role_assignments', 'account_refresh_tokens', 'customer_records'] as $table) {
            EntityPermission::ensureFor($table);
            $user->givePermissionTo("entity.{$table}.update");
            $this->assertFalse(EntityPermission::allows($user, $table, 'update'));
        }
    }

    public function test_even_platform_password_writes_use_dedicated_user_routes(): void
    {
        $user = $this->makeUser('super-admin');
        foreach (['create', 'update', 'delete'] as $action) {
            $this->assertFalse(EntityPermission::allows($user, 'users', $action));
        }
        $this->assertTrue(EntityPermission::allows($user, 'users', 'view'));
    }

    public function test_account_state_gate_preserves_legacy_sessions_until_approved_migration(): void
    {
        $user = $this->makeUser('super-admin');
        $user->update(['status' => 'inactive']);
        // Without verified users.status/backfill, new lifecycle enforcement
        // must not unexpectedly log out existing administrators.
        config()->set('foundation.account_state_enforcement_enabled', false);
        $this->actingAs($user)->get('/_admin-only')->assertOk();
        $this->assertTrue(app(\Iquesters\Foundation\Security\TenantAuthorization::class)->isPlatformAdmin($user));

        config()->set('foundation.account_state_enforcement_enabled', true);
        $this->get('/_admin-only')->assertUnauthorized();
        $this->assertFalse(app(\Iquesters\Foundation\Security\TenantAuthorization::class)->isPlatformAdmin($user));
    }

    public function test_inactive_super_admin_cannot_use_legacy_admin_routes(): void
    {
        config()->set('foundation.account_state_enforcement_enabled', true);
        $user = $this->makeUser('super-admin');
        $user->update(['status' => 'inactive']);
        $this->actingAs($user)->get('/_admin-only')->assertUnauthorized();
    }
}
