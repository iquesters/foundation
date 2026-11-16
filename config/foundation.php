<?php

/*
| Deploy-time settings read while routes are registered (so they can't come
| from the DB: they'd be frozen into `route:cache`). Merged by
| FoundationServiceProvider; publish to config/foundation.php to override.
|
| Runtime module settings (e.g. admin_roles) live in the DB-backed conf
| (see docs/developer/configuration.md).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Module APIs  (/api/{module}/{version}/...)
    |--------------------------------------------------------------------------
    |
    | Modules declare their API versions in composer.json
    | (extra.platform.api). Keys below are the module prefix from that file.
    |
    | middleware:  extra middleware for every version of a module's API.
    |              Authentication is declared in each route file, because a
    |              module can have public endpoints (e.g. the setup form schema).
    | rate_limits: "requests,minutes" per module and version (throttle).
    |
    */

    'api' => [
        'middleware' => [],

        'rate_limits' => [
            'foundation' => ['v1' => '600,1'],
            // Tables prefetch pages and forms load schemas: keep this generous.
            'user-interface' => ['v1' => '300,1'],
        ],
    ],
];
