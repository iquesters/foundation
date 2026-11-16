# Upgrading iquesters/foundation

## 1.x → 2.0

Upgrade foundation, user-interface, user-management and organisation together (they are released
as one 2.0 set).

### Requirements
- PHP **8.3+**, Laravel **12 or 13**, spatie/laravel-permission **8**.

### Steps
1. Update the packages:
   ```bash
   composer require "iquesters/foundation:^2.0" "iquesters/user-interface:^2.0" "iquesters/user-management:^2.0" "iquesters/organisation:^2.0" -W
   ```
2. Run migrations (hardens tables, removes the old `api_conf` settings):
   ```bash
   php artisan migrate
   ```
3. Sync per-entity permissions and encrypt stored secrets:
   ```bash
   php artisan entity:sync-permissions
   php artisan conf:encrypt-secrets
   ```
4. Clear caches: `php artisan optimize:clear`.

### Breaking changes
- **Admin roles** come from the DB setting `admin_roles` (foundation conf, default `super-admin,admin`),
  editable on the config screen. `config('foundation.admin_roles')` / `FOUNDATION_ADMIN_ROLES` are gone.
  `super-admin` is always allowed. In code, use `EnsureAdmin::roles()`.
- **Module APIs** are served at `/api/{module}/{version}/...` and declared in each module's
  `composer.json` (`extra.platform.api`). The old `/api/{platform}/{module}/{version}` routes,
  `ApiRouteServiceProvider`, `PackageDiscovery`, `ValidatePlatformVersion` and `platform_versions`
  are removed. See `docs/developer/api.md`.
- `POST /api/dispatch-queue` → `POST /api/foundation/v1/dispatch-queue` (old URL is a deprecated alias).
- `config/foundation.php` only holds API settings (`api.middleware`, `api.rate_limits`). If you
  published it before, re-publish or remove your copy.
- Per-entity permissions `entity.{table}.view|create|update|delete` guard the generic entity API;
  give non-admin roles the permissions they need after `entity:sync-permissions`.

### For module authors
- Extend `BaseServiceProvider` and add a `Package\{Module}PackageInfo`; set `$view_namespace` /
  `$auto_conf_module` there when they differ from the conventions.
- One-time install steps: `$this->onPackageDiscover(fn () => ...)`; publish vendor files with
  `InstallPublisher`; add traits/fillable to the app's `User` model with `UserModelPatcher`.
- Public assets: subclass `Support\ModuleAssets` (declare the slug).
