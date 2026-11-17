# Changelog

All notable changes to this package are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [2.0.2] – 2026-11-17

### Fixed
- Master data detail page (`/master-data/{id}`) failed with "Undefined variable $selectNode" (typo for `selectedNode`).

## [2.0.1] – 2026-11-17

### Fixed
- Meta-table hardening migration used MySQL-only `DELETE … JOIN`; the duplicate cleanup is now portable, so fresh installs migrate on SQLite and PostgreSQL too.

## [2.0.0] – 2026-11-17

### Settings engine
- **Breaking:** module settings are stored in a new `module_settings` table (JSON per module, only values that differ from the code defaults) instead of `master_data_metas`. The migration converts existing values; old rows are kept.
- Typed, validated settings: `BaseConf` converts stored values to the declared property types, applies `rules()`, falls back to the default for invalid values (logged), and saves through `update()` (throws `ValidationException`).
- Seeders no longer write settings, so admin changes can't be overwritten; new defaults reach existing installs.
- `isset()` / `??` on settings now see the real value (they always returned the fallback before).
- Nested settings no longer query the database (removes the `No master_data entry found` log noise).
- Admin settings screen with typed inputs, defaults and reset; `conf:encrypt-secrets` works on `module_settings`.

### Housekeeping
- Removed commented-out code and emoji from log messages; added Pint/Larastan (`composer format|lint|analyse`), a test CI workflow (PHP 8.3/8.4 × Laravel 12/13), `.gitattributes`, `SECURITY.md`, `CONTRIBUTING.md`, `UPGRADE.md` and composer metadata.

### Breaking
- Requires PHP 8.3, Laravel 12/13 and spatie/laravel-permission 8.
- Foundation no longer depends on higher-level modules; real dependencies are declared in composer.json.

### Added
- `ensure.admin` middleware (`EnsureAdmin`) guarding admin, entity, config, builder and queue routes; roles from `foundation.admin_roles`.
- Per-entity permissions `entity.{table}.view|create|update|delete` and a sync command.
- Encrypted config secrets (`enc:` prefix) and a command to encrypt existing ones.
- `Support\PackageAsset` for serving module public files safely, preferring published static copies.
- `Support\UserModel` resolves the user model from config.
- `Loggable` trait: static methods, context arrays and a `[Class::method(line)]` prefix.
- Testbench test suite.

### Changed
- Logging across the package uses `Loggable`; request/response logging is one INFO summary per request with details at DEBUG.
- Entity builder keeps validation keys; publishing adds unique keys and audit foreign keys.

### Changed (module APIs)
- **Breaking:** module APIs are served at `/api/{module}/{version}/...` (one version layer), declared in each module's
  `composer.json` (`extra.platform.api`) and registered by `Routing\ModuleApi` from `BaseServiceProvider`.
  Adds `GET /api` (index), `GET /api/{module}/{version}` (status), per-module rate limits/middleware from
  `config/foundation.php` (`api.*`), and `route:cache` support. See `docs/developer/api.md`.
- Removed the old `/api/{platform}/{module}/{version}` router (`ApiRouteServiceProvider`, `PackageDiscovery`,
  `ValidatePlatformVersion`, `platform_versions`, `routes/api.v1.php`, `routes/platform.api.v1.php`) and the
  `api_conf` setting of foundation/user-interface/user-management/organisation (migration removes the stored rows).
  `ApiConf` / `ApiVersion` stay, deprecated, for other modules that still declare them.
- `POST /api/foundation/v1/dispatch-queue`; `/api/dispatch-queue` kept as a deprecated alias.
- `config/foundation.php` is now merged and only holds deploy-time API settings (`api.middleware`, `api.rate_limits`).
- **Breaking:** admin roles moved to the DB conf: `FoundationConf::admin_roles` (`ADMIN_ROLES`, default
  `super-admin,admin`) replaces `config('foundation.admin_roles')` / `FOUNDATION_ADMIN_ROLES` (which was never
  loaded anyway). `super-admin` is always allowed. Use `EnsureAdmin::roles()`.
- Module API route files are named `routes/api.v1.php` (one per version).
- New `Support\ModuleAssets` base: modules' `Assets` classes only declare their slug (asset route, URLs, logo resolution).
- Entity pages show the versioned entity API URLs, keyed by table name (they showed the slug and two endpoints that don't exist).

### Added (module providers)
- `Support\UserModelPatcher`: modules add their traits and fillable fields to the app's `User` model on
  `composer install/update` (idempotent; only edits files inside `app/`).
- `Support\InstallPublisher`: publish a vendor migration/config once (no duplicate timestamped migrations).
- `BaseServiceProvider::onPackageDiscover()` hook for install-time steps.
- `PackageInfo` overrides: `$view_namespace`, `$extra_routes_paths`.

### Performance
- `BaseServiceProvider` builds its `PackageInfo` once per provider (it re-ran folder auto-discovery ~8 times per boot).
- `ConfProvider::from()` reuses one conf instance per module for the request (reset per request / queue job;
  `ConfProvider::forget()` and `clearCache()` drop it). Previously every call rebuilt the conf, costing an
  `information_schema` query and cache reads each time (hundreds per page).
- `BaseConf` checks for the `master_data` table once per process.
- `Module::getForUser()` eager-loads metas; `Module::getMeta()` / `Navigation::getMeta()` use loaded metas.

### Fixed
- `SeederCommand` used an undeclared property (PHP 8.2+ dynamic-property deprecation).
- Admin check runs before route model binding; `LARAVEL_START` guard.
- `/navigations` crashed on an undefined `$tableSchema`.
- `MasterData` meta setters accept a null user id.
- Database: unique meta keys, audit FKs, nullable `master_data.value`.

### Security
- Admin-only routes, authenticated queue API, encrypted secrets, SRI on CDN assets.
- Path traversal in module asset routes (`/vendor/{module}/{path}`).
