<?php

namespace Iquesters\Foundation\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected $guard_name = 'web';
}
