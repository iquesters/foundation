# Module APIs (`/api/{module}/{version}`)

Every module's JSON API is served under one scheme:

```
/api/{module}/{version}/...        e.g. /api/user-interface/v1/entity/list/users
```

One version per module. A breaking change ships as a new version (`v2`) next to the old one, so
existing clients keep working until they move.

## Declaring a module API

The module's `composer.json` is the single source of truth (nothing is read from the database, so
`php artisan route:cache` works):

```json
"extra": {
  "platform": {
    "api": {
      "prefix": "user-interface",
      "versions": {
        "v1": "routes/api.v1.php"
      }
    }
  }
}
```

`BaseServiceProvider` reads this (via `PackageInfo::getApi()`) and registers each version with
`Routing\ModuleApi`. Nothing else is needed in the module's service provider.

## Writing the route file

`routes/api.v1.php` contains only the module's routes:

- **No `/api` prefix and no version**: both are added for you.
- **Route names are relative**: they get the prefix `api.{module}.{version}.`
  (`->name('entity.list')` becomes `api.user-interface.v1.entity.list`).
- **Authentication is declared here**, per route or group (`auth:sanctum`), because a module can
  have public endpoints too (e.g. the form schemas used on the login and setup pages).

```php
Route::get('noauth/form/{slug}', [FormController::class, 'getNoAuthFormSchema'])->name('noauth.form');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('entity/list/{entity_name}', [DynamicEntityController::class, 'list'])->name('entity.list');
});
```

## What every API route gets

| | |
|---|---|
| Middleware | `api`, `RequestMiddleware` (request id, logging), `ResponseMiddleware` (standard envelope) |
| Response | wrapped in the standard envelope: payload in `response_schema.data` |
| Status | `GET /api/{module}/{version}` → `{module, version}` |
| Index | `GET /api` → every module and its versions |

Per module, from `config/foundation.php` (merged by foundation, publish to override):

```php
'api' => [
    'middleware'  => ['user-interface' => ['some.middleware']],   // all versions
    'rate_limits' => ['user-interface' => ['v1' => '300,1']],      // throttle: requests,minutes
],
```

## Current APIs

| Module | Versions | Notes |
|---|---|---|
| foundation | v1 | `POST dispatch-queue` (only when smart-messenger + integration are installed) |
| user-interface | v1 | entity CRUD, form/table schemas, component templates (see user-interface `docs/dynamic-entity-api.md`) |
| user-management | – | none (auth runs on web routes: it needs the session) |
| organisation | – | none yet |

## Deprecated

- The unversioned user-interface URLs (`/api/entity/...`, `/api/noauth/form/...`, `/api/form/...`,
  `/api/auth/table/...`, `/api/components/templates/...`) and foundation's `/api/dispatch-queue` are
  aliases kept for existing callers. The modules' own JS uses the versioned URLs. They will be removed
  in a later release.
- The old platform-versioned scheme (`/api/v1/{module}/v1`, `ApiRouteServiceProvider`,
  `platform_versions`, the DB `api_conf` setting) is removed. `ApiConf` / `ApiVersion` remain only so
  modules that still declare an `api_conf` property keep loading.
