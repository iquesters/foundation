# Configuration (Developer)

Foundation has two kinds of settings.

## DB-backed conf (runtime, editable)

Read through `ConfProvider::from(Module::FOUNDATION)`; defaults in `src/Config/FoundationConf.php`,
written to `master_data` / `master_data_metas` by `php artisan foundation:seed`.

| Key | DB meta key | Default | Purpose |
|---|---|---|---|
| `admin_roles` | `ADMIN_ROLES` | `super-admin,admin` | Comma-separated roles allowed through `ensure.admin` (admin, entity, config, form/table builder, queue routes) and treated as admins by per-entity permissions |

`super-admin` is always allowed, whatever `admin_roles` says, so a wrong value can't lock every admin
out. An empty value falls back to the default. Use `EnsureAdmin::roles()` to get the effective list.

## `config/foundation.php` (deploy-time)

Settings read while routes are registered, so they can't come from the DB (they would be frozen into
`route:cache`). Merged by `FoundationServiceProvider`; publish the file to override.

| Key | Purpose |
|---|---|
| `api.middleware.{module}` | Extra middleware for every version of a module's API |
| `api.rate_limits.{module}.{version}` | `"requests,minutes"` throttle per module API version |

See [api.md](api.md) for module API routing.

## Module public assets

A module's `public/` files are handled by a subclass of `Support\ModuleAssets` that declares its slug
(e.g. `Iquesters\UserInterface\Support\Assets` → `userinterface`):

- `Assets::registerRoutes()` in the provider's `boot()`: `/module-assets/{slug}/{path}` (+ legacy `/vendor/{slug}/{path}`),
  serving only files inside `public/`.
- `Assets::url('js/app.js')`: the published copy in `public/vendor/{slug}` when current, else the route.
- `Assets::resolveUrl($value)`: a configured asset value (URL, absolute path, app file, `pkg::file`) → URL.
- Publish `public/` under the `laravel-assets` tag so `composer update` refreshes the published copy.
