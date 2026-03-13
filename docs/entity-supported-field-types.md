# Entity Supported Field Types

## Scope
This document defines the first-pass field types supported by the entity builder for both MySQL and MariaDB.

## Supported Primary Field Types
- `string`
- `text`
- `longtext`
- `integer`
- `decimal`
- `boolean`
- `date`
- `datetime`
- `time`

## Supported Meta Field Types
- `string`
- `text`
- `longtext`
- `integer`
- `decimal`
- `boolean`
- `date`
- `datetime`
- `time`

## Supported Input Types
- `text`
- `textarea`
- `number`
- `email`
- `date`
- `datetime-local`
- `time`
- `checkbox`
- `select`

## Notes
- `json` is intentionally excluded from this first compatibility pass because MySQL and MariaDB differ in storage semantics.
- `enum` is intentionally excluded until the entity builder supports option definition and validation.
- `datetime` is stored as `timestamp` in the current builder implementation.
- Generated form schema includes both primary fields and displayable meta fields.
- Generated create form schema now targets the generic dynamic entity API using the entity table name, not the entity slug.
- Backend create and update flows validate submitted values against the stored entity `fields` and `meta_fields` definitions before persistence.
- Main table values are submitted as top-level payload keys; meta values are submitted under the `meta` object.
