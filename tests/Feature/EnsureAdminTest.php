<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\Route;
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

    public function test_admin_roles_come_from_config(): void
    {
        config(['foundation.admin_roles' => ['owner']]);

        $this->actingAs($this->makeUser('super-admin'))->get('/_admin-only')->assertForbidden();
        $this->actingAs($this->makeUser('owner'))->get('/_admin-only')->assertOk();
    }

    public function test_user_model_without_roles_fails_closed(): void
    {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';
            protected $guarded = [];
        };
        $user = $user->newQuery()->create(['name' => 'x', 'email' => 'noroles@example.test', 'password' => 'x']);

        $this->actingAs($user)->get('/_admin-only')->assertForbidden();
    }
}
