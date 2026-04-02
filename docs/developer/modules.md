# Modules (Developer Documentation)

## Overview

The system follows a modular architecture using `iquesters/*` packages integrated into a Laravel host application.

---

## Architecture

* Laravel acts as host application
* Modules provide actual functionality
* Loaded via Composer auto-discovery

---

## Host Responsibilities

* Bootstrapping Laravel
* Registering dependencies
* Providing base routing
* Running local seeders

---

## Installed Modules

### Foundation

Core system framework, job handling, module registration

### User Interface

UI rendering, schema-driven forms and tables

### User Management

Authentication, roles, permissions

### Organisation

Organisation and team hierarchy

### Integration

External system integration + jobs

### Help Support

Documentation and help center

### Dev

Development tools and diagnostics

---

## Module Loading Flow

1. Composer installs package
2. Service provider boots module
3. Seeder registers module
4. Metadata stored in DB
5. Features become available

---

## Seeders

Modules provide their own seeders:

* FoundationSeeder
* UserManagementSeeder
* etc.

Local seeders:

* QueueSeeder
* WorkflowSeeder

---

## Notes

* Business logic resides in packages
* Local app acts as container
* Highly scalable architecture

---
