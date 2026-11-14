# Changelog

All notable changes to this package are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [Unreleased] – 2.0.0

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

### Fixed
- Admin check runs before route model binding; `LARAVEL_START` guard.
- `/navigations` crashed on an undefined `$tableSchema`.
- `MasterData` meta setters accept a null user id.
- Database: unique meta keys, audit FKs, nullable `master_data.value`.

### Security
- Admin-only routes, authenticated queue API, encrypted secrets, SRI on CDN assets.
- Path traversal in module asset routes (`/vendor/{module}/{path}`).
