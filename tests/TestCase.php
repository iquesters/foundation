<?php

namespace Iquesters\Foundation\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Iquesters\Foundation\FoundationServiceProvider;
use Iquesters\Foundation\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;

#[WithMigration]
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            FoundationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('permission.cache.store', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function makeUser(?string $role = null): User
    {
        $user = User::create([
            'name' => 'Test ' . Str::random(6),
            'email' => Str::random(10) . '@example.test',
            'password' => bcrypt(Str::random(20)),
        ]);

        if ($role) {
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        }

        return $user;
    }
}
