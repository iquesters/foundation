# Messenger Modules Documentation

## Overview

The `messenger` project is a Laravel 12 host application with a thin local app layer and most platform features provided by modular `iquesters/*` packages.

At the application level, the local project currently contains:

- a minimal Laravel bootstrap layer
- the default `/` welcome route
- local seeders for queue and workflow setup
- package registration through Composer auto-discovery

This means Messenger behaves primarily as a container for reusable modules rather than placing business logic directly inside `app/`.

## Host Application Responsibilities

The local Messenger repository itself is responsible for:

- bootstrapping Laravel through [bootstrap/app.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/bootstrap/app.php)
- loading only the local [AppServiceProvider](/c:/Users/DEBAYAN/Desktop/Clone/messenger/app/Providers/AppServiceProvider.php)
- exposing the root route in [routes/web.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/routes/web.php)
- declaring package dependencies in [composer.json](/c:/Users/DEBAYAN/Desktop/Clone/messenger/composer.json)
- defining local support seeders in [database/seeders](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders)

Notably, the root [DatabaseSeeder](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders/DatabaseSeeder.php) currently seeds only a test user. The module-specific seeders live inside the installed packages.

## Installed Modules

### 1. Foundation

Package: `iquesters/foundation`

Purpose:

- base module framework
- module registration and metadata seeding
- entity registry
- module configuration storage
- queue and job management UI
- shared base job abstraction

What it contributes:

- `BaseServiceProvider` for loading package routes, views, migrations, commands, middleware, and config conventions
- `BaseSeeder` for seeding modules, permissions, entities, config, and auto-discovered jobs
- routes for entities, module-role assignment, config, jobs, navigations, and queue management
- sidebar entries for master data, modules, entities, queues, jobs, failed jobs, and completed jobs

Key files:

- [vendor/iquesters/foundation/src/System/Providers/BaseServiceProvider.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/src/System/Providers/BaseServiceProvider.php)
- [vendor/iquesters/foundation/database/seeders/FoundationSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/database/seeders/FoundationSeeder.php)
- [vendor/iquesters/foundation/routes/web.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/routes/web.php)
- [vendor/iquesters/foundation/src/Jobs/BaseJob.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/src/Jobs/BaseJob.php)

### 2. User Interface

Package: `iquesters/user-interface`

Purpose:

- generic UI rendering layer
- form schema and table schema management
- navigation-related admin screens
- theme-related seed data

What it contributes:

- UI list and schema-driven rendering
- seed data for `form_schemas`, `table_schemas`, and navigation views
- theme master data such as available themes and current theme

Sidebar/menu intent:

- Forms
- Tables
- Navigation
- All Organisations

Key files:

- [vendor/iquesters/user-interface/database/seeders/UserInterfaceSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/user-interface/database/seeders/UserInterfaceSeeder.php)
- [vendor/iquesters/user-interface/src/UserInterfaceServiceProvider.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/user-interface/src/UserInterfaceServiceProvider.php)

### 3. User Management

Package: `iquesters/user-management`

Purpose:

- authentication and user lifecycle
- role and permission management
- profile and account-related flows
- Sanctum and Socialite integration support

What it contributes:

- admin/manageable users, roles, and permissions
- module permissions for CRUD on users, roles, and permissions
- entity definitions for `users`, `roles`, and `permissions`
- user meta fields such as login info, registration info, timezone, locale, and Google ID

Sidebar/menu intent:

- Users
- Roles
- Permissions

Key files:

- [vendor/iquesters/user-management/database/seeders/UserManagementSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/user-management/database/seeders/UserManagementSeeder.php)
- [vendor/iquesters/user-management/src/UserManagementServiceProvider.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/user-management/src/UserManagementServiceProvider.php)

### 4. Organisation

Package: `iquesters/organisation`

Purpose:

- organisation and team hierarchy support
- organisation CRUD and linking
- permission-scoped organisation management

What it contributes:

- organisation models and traits
- CRUD permissions for organisations
- organisation listing route used by other modules

Sidebar/menu intent:

- All Organisations

Key files:

- [vendor/iquesters/organisation/database/seeders/OrganisationSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/organisation/database/seeders/OrganisationSeeder.php)
- [vendor/iquesters/organisation/src/OrganisationServiceProvider.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/organisation/src/OrganisationServiceProvider.php)

### 5. Integration

Package: `iquesters/integration`

Purpose:

- external system integrations
- integration administration screens
- vector sync execution

What it contributes:

- integration index and configuration flows
- integration-specific seed logic through `IntegrationModuleSeeder`
- the only currently discovered concrete `BaseJob` implementation in the installed packages: `SyncVectorJob`

Sidebar/menu intent:

- Integrations

Key files:

- [vendor/iquesters/integration/database/seeders/IntegrationSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/integration/database/seeders/IntegrationSeeder.php)
- [vendor/iquesters/integration/src/Jobs/SyncVectorJob.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/integration/src/Jobs/SyncVectorJob.php)

### 6. Help Support

Package: `iquesters/help-support`

Source type:

- local path repository linked from `../help-support`
- symlinked into Composer vendor resolution

Purpose:

- help center and docs UI for modules
- module-aware support/document browsing

What it contributes:

- Help Center screen
- module document browsing routes
- docs file and docs list endpoints

Sidebar/menu intent:

- Help Center

Key files:

- [../help-support/database/seeders/HelpSupportSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/help-support/database/seeders/HelpSupportSeeder.php)
- [../help-support/routes/web.php](/c:/Users/DEBAYAN/Desktop/Clone/help-support/routes/web.php)

### 7. Dev

Package: `iquesters/dev`

Purpose:

- development and diagnostic tooling
- vector-response inspection
- manual vector trigger utilities

What it contributes:

- views and routes for vector responses
- trigger-vector controller flow
- seed data for `vector_responses` admin table view

Sidebar/menu intent:

- Vector Responses
- Vector Responses Old
- Trigger Vector

Key files:

- [vendor/iquesters/dev/database/seeders/DevSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/dev/database/seeders/DevSeeder.php)
- [vendor/iquesters/dev/src/Http/Controllers/TriggerVectorController.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/dev/src/Http/Controllers/TriggerVectorController.php)

## Module Loading Model

The package modules are loaded through Composer Laravel auto-discovery, not by manually listing each provider in the Messenger app.

The common loading pattern is:

1. Composer installs the package and exposes its service provider.
2. The service provider boots routes, views, migrations, commands, and seeders.
3. The package seeder registers the module in the `modules` table.
4. Module metadata and sidebar definitions are stored in `module_metas`.
5. Entities, permissions, config defaults, and jobs are seeded from package conventions.

## Messenger-Specific Seeders Outside the Package Model

The local project also contains standalone seeders:

- [database/seeders/RoleSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders/RoleSeeder.php)
- [database/seeders/QueueSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders/QueueSeeder.php)
- [database/seeders/WorkflowSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders/WorkflowSeeder.php)

These are Messenger-specific and separate from the package module seeders.

Important current state:

- they are present in the repository
- they are not referenced by the root `DatabaseSeeder`
- they appear intended for manual or command-driven setup

## Summary

Messenger is currently best understood as:

- a Laravel host application
- backed by reusable `iquesters` modules
- with Foundation acting as the platform core
- with local seeders adding Messenger-specific workflow and queue metadata

If the project evolves, the most likely place for new domain functionality will be package modules or a new Messenger-specific package rather than the local `app/` folder.
