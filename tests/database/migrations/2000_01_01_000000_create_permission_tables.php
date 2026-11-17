<?php

use Spatie\Permission\PermissionServiceProvider;

// spatie/laravel-permission ships its migration as a publishable stub.
$provider = new ReflectionClass(PermissionServiceProvider::class);

return include dirname($provider->getFileName(), 2).'/database/migrations/create_permission_tables.php.stub';
