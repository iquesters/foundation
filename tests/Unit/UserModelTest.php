<?php

namespace Iquesters\Foundation\Tests\Unit;

use Iquesters\Foundation\Support\UserModel;
use Iquesters\Foundation\Tests\Fixtures\User;
use Iquesters\Foundation\Tests\TestCase;

class UserModelTest extends TestCase
{
    public function test_resolves_the_configured_user_model(): void
    {
        $this->assertSame(User::class, UserModel::name());

        $user = $this->makeUser();

        $this->assertTrue(UserModel::findOrFail($user->id)->is($user));
        $this->assertSame($user, UserModel::findOrFail($user));
        $this->assertSame(1, UserModel::query()->count());
    }
}
