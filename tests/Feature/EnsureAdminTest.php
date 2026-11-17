<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Enums\Module;
use Iquesters\Foundation\Http\Middleware\EnsureAdmin;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Tests\TestCase;

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

    public function test_default_admin_roles_are_allowed(): void
    {
        $this->actingAs($this->makeUser('super-admin'))->get('/_admin-only')->assertOk();
        $this->actingAs($this->makeUser('admin'))->get('/_admin-only')->assertOk();
    }

    public function test_admin_roles_come_from_the_foundation_conf(): void
    {
        $this->setAdminRoles('owner');

        $this->actingAs($this->makeUser('owner'))->get('/_admin-only')->assertOk();
        $this->actingAs($this->makeUser('admin'))->get('/_admin-only')->assertForbidden();
    }

    public function test_super_admin_is_always_allowed(): void
    {
        $this->setAdminRoles('owner');
        $this->actingAs($this->makeUser('super-admin'))->get('/_admin-only')->assertOk();

        $this->setAdminRoles(' , ');
        $this->assertSame(['super-admin', 'admin'], EnsureAdmin::roles());
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
        $user = $user->newQuery()->create(['name' => 'x', 'email' => 'noroles@example.test', 'password' => 'x']);

        $this->actingAs($user)->get('/_admin-only')->assertForbidden();
    }
}
