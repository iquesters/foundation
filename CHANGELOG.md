# Changelog

All notable changes to this package are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [Unreleased]

### Added
- **Business entities:** one record made of several entity tables, e.g. a customer with its address (`has_one`) and its company (`belongs_to`).
  - **Admin builder** at Admin → Business Entities (`/business-entity`, admins only):
    - choose the root entity and linked parts;
    - pick their fields and meta fields;
    - save to generate a form schema (`business-{slug}-form`) and a table schema (`business-{slug}-table`) that use the business entity API in iquesters/user-interface.
  - **Storage:** `business_entities` and `business_entity_metas` tables (migration `2026_11_18_000001`), with the `BusinessEntity` and `BusinessEntityMeta` models.
  - **Definition checks:**
    - `BusinessEntityDefinition` checks the structure, the write order, the link columns and the flat field keys (`{alias}__{field}`);
    - `BusinessEntityDefinitionValidator` checks the definition against the registered entities and their tables. Each part's table always comes from its entity.
  - **Docs:** `docs/developer/business-entities.md`.
- `FormSchemaGenerator::build()` and `TableSchemaGenerator::build()`: generate a schema for any resource (display name, schema entity key, endpoints). `generate(Entity)` output is unchanged.
- **Entity data API, moved here from iquesters/user-interface:** foundation is now the core data module and user-interface keeps presentation.
  - **API:** `/api/foundation/v1/entity/{list|show|store|update|delete}/...` (`DynamicEntityController`) and the new `/api/foundation/v1/business-entity/...` (`BusinessEntityDataController`).
  - **Services:**
    - `Services\Entity\*`: repository, schema resolver, validator, normalizer, scope, presenter, error mapper;
    - `Services\BusinessEntity\BusinessEntityRecords`;
    - `Http\Query\DynamicEntityListQuery` and `ParsesListQuery`;
    - `Constants\EntityListParams` and `Contracts\EntityScopeResolver`.
  - **Business entity records:**
    - one transaction per save, in link order, with link columns filled in automatically;
    - each part is validated with its entity's rules, and all errors are reported together;
    - `entity.{table}.{action}` permission is needed on every part's table;
    - delete soft-deletes the parts the record owns and keeps shared ones.
  - **Docs:** `docs/developer/entity-api.md` and `docs/developer/business-entity-api.md`.
- **Foundation's own `public/` files:** served at `/module-assets/foundation/...` and published to `public/vendor/foundation` with the `laravel-assets` tag (`Support\Assets`).
- **Business entities: all stored Laravel relations** (definition version 2; version 1 is read unchanged):
  - **Types:** `has_one`, `belongs_to`, `morph_one`, `has_many`, `morph_many`, `belongs_to_many`, `morph_to_many`, `morphed_by_many`. `has_*_through` and `morph_to` are not offered, because they have nothing to store.
  - **Modes:** *compose* (rows edited inside the record) or *reference* (points at existing rows, picked in the form).
  - **Lists of rows** (`"{alias}": [...]`): on update the sent list replaces the current rows.
  - **References and pivots:** synced; the new `GET business-entity/options/{slug}/{alias}` lists their choices.
  - **Platform models:** users, roles and permissions can be referenced, e.g. roles through Spatie's `model_has_roles` (`BusinessEntityModels`; modules register their own).
    - An entity over a model's table (e.g. `users`) uses the model's morph class (`App\Models\User`) in morph columns, so a "user with roles" business entity reads and writes the same roles as Spatie.
  - **Extensible:** relation types are `Relations\RelationType` descriptions in `Relations\RelationTypes`; modules add their own with the `business-entity.relation-types` tag.
  - **Configuration only:** every table, column and pivot a link uses must exist. A missing pivot is reported (`InvalidBusinessEntityDefinition::$missingPivots`), never created.
  - **Generated forms:** a `repeater` field for a list of rows; a `select` or `checkbox` field with `optionsFrom.url` for a reference.
- **Business entity builder (#29):**
  - an "Add entity" picker in the UI modal, with entities already used disabled and a primary (root) choice;
  - each relation as a sentence;
  - link, morph and pivot column pickers, with suggestions;
  - a "Needs pivot table" warning with a link to create the pivot entity;
  - a **review step** that checks the definition on the server (`POST /business-entity/check`) and shows the whole structure before saving;
  - `GET /business-entity/columns?table=` for the column pickers.
- **Business entity records name their references:** `"labels": {"roles": ["Admin", "Editor"], "company": "Acme"}`, read from each reference's label column. Record components use it, e.g. a user's roles as badges.
- **Business entity screens, plain language:**
  - **Builder:** a "How it works" guide; numbered sections (Details, Where it appears, Entities and how they link, Review and save); help text under each field and each screens option.
  - **Errors:** problems are listed one per line and named after the part, e.g. "Roles: the link table model_has_roles has no column "user_id" in the link table. Pick one of its columns."
  - **Success messages** say what is ready.
  - **List page:** an "Appears as" column and a friendlier empty state.
- **"Use the form in" can be undone:**
  - The table's own form is remembered and put back when the option is cleared or the business entity is deleted.
  - Regenerating that entity's table keeps the business entity's form.
  - Deleting a business entity also deactivates its generated form and list.
- **Business entity screens:**
  - `output` can be *a form and its own list* (default), *a form only*, or *API only*. "API only" is for a module's own form.
  - **Use the form in** an existing table: that table's Create button and rows open the business entity's form (`use_in_table`).
  - The builder has both options. Definition files take the keys `output` and `use_in_table`.
- Generated forms:
  - `password` and `tel` inputs get their own types; passwords were a plain text field before.
  - A password is required on create; on edit it is left out when blank ("leave blank to keep").
- **Definition files:** `business-entity:export` and `business-entity:sync [path] [--dry-run]`, in `database/business-entities/*.json`, with entities named by table.
- **Events:** `BusinessEntityDefinitionChanged` and `BusinessEntityRecordSaved`.
- **`BusinessEntityService`:** creating, updating, deleting and syncing definitions, shared by the builder and the commands.
- **Schema generation services (#30):**
  - `Services\Schema\EntitySchemaService` generates, stores and re-finds an entity's form and table schemas (moved out of `EntityController`);
  - `Services\Schema\GeneratedSchemaStore` stores generated schemas for entities and business entities;
  - `Support\EntityTableName` provides entity table names;
  - `FormSchemaGenerator::formFields()`, and `build()` takes extra ready-made fields.

### Changed
- **Module APIs accept the web login from the app's own pages.** Sanctum's `EnsureFrontendRequestsAreStateful` is added to every `/api/{module}/{version}` route, so apps no longer need `$middleware->statefulApi()`.
  - Before this, in an app without it, module tables showed "Unauthenticated." and option lists (e.g. Roles) stayed empty.
  - This only applies to requests from Sanctum's stateful domains (with CSRF on writes); token callers are unchanged.
- Requires `laravel/sanctum` `^4.0` (the entity API uses `auth:sanctum`).
- Generated entity form schemas use `/api/foundation/v1/entity/...`. Schemas generated before still work through user-interface's deprecated aliases.
- `EntityController` only handles requests; schema work goes through `EntitySchemaService`.
  - Creating an entity now reuses a generated schema with the same slug (or the same table as its `entity`) instead of creating a duplicate.
  - "Generate table schema" no longer fails when the form schema uid was cached on the entity.

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
