<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform API Versions
    |--------------------------------------------------------------------------
    |
    | These are the globally supported platform API versions.
    | Any request using a version not listed here will return 404.
    |
    */

    'platform_versions' => [
        'v1',
        'v2',
    ],


    /*
    |--------------------------------------------------------------------------
    | Per-Package Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all API routes of a package.
    | Example: auth:sanctum, verified, role checks, etc.
    |
    */

    'middleware' => [

        'iquesters/foundation' => [
            'auth:sanctum',
        ],

        'iquesters/user-interface' => [
            'auth:sanctum',
        ],

        'iquesters/user-management' => [
            'auth:sanctum',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits are applied per package and per package version.
    | Format: "requests,minutes"
    |
    */

    'rate_limits' => [

        'iquesters/foundation' => [
            'v1' => '600,1',
        ],

        'iquesters/user-interface' => [
            'v1' => '60,1',
            'v2' => '120,1',
        ],

        'iquesters/user-management' => [
            'v1' => '100,1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When true:
    | - Enabled packages MUST declare platform.api in composer.json
    | - Missing or invalid API manifests throw exceptions
    |
    */

    'strict' => true,
];