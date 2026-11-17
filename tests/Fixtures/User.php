<?php

namespace Iquesters\Foundation\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected $guard_name = 'web';
}
