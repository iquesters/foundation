# Dynamic Entity API

## Scope
This document describes the generic dynamic entity API exposed by `DynamicEntityController`.

## Endpoints
- `GET /api/foundation/v1/entity/list/{entity_name}`
- `GET /api/foundation/v1/entity/show/{entity_name}/{data_uid}`
- `POST /api/foundation/v1/entity/store/{entity_name}`
- `PUT /api/foundation/v1/entity/update/{entity_name}/{data_uid}`
- `DELETE /api/foundation/v1/entity/delete/{entity_name}/{data_uid}`

All endpoints need a Sanctum token. Free-text search: `?search=term` (text columns and meta values).
The old `/api/entity/...` and `/api/user-interface/v1/entity/...` URLs (from when this API lived in iquesters/user-interface) still work as deprecated aliases and will be removed in 3.0.
Routing (`/api/{module}/{version}`) is explained in `api.md`.

## List Flow
1. Resolve the target table from `{entity_name}` and confirm it exists.
2. Resolve the matching entity definition from the `entities` table by looking up the entity meta `table_name`.
3. Build main-field definitions from `entities.fields` and meta-field definitions from `entities.meta_fields`.
4. Accept reserved list query params for pagination and sorting:
   - `offset`
   - `length`
   - `sort`
5. Treat any non-reserved query parameter as a filter candidate.
6. Apply filters against declared main fields directly on the entity table.
7. Apply filters against declared meta fields through the entity meta table using `ref_parent`, `meta_key`, and `meta_value`.
8. Apply multi-column sort in the order requested.
9. Count the filtered result set.
10. Return the paginated rows in the standardized API response format.

## List Query Contract
- Flat equality filters are supported for simple cases.
- Operator-based filters are supported using bracket syntax on the field name.
- Multi-column sort is supported using a comma-separated `sort` value.
- Pagination is controlled with `offset` and `length`.

### Reserved query parameters
- `offset`
- `length`
- `sort`

### Supported filter operators
- `eq`
- `neq`
- `gt`
- `gte`
- `lt`
- `lte`
- `like`
- `starts`
- `ends`
- `in`

### Filter examples
- `GET /api/foundation/v1/entity/list/users?status=active`
- `GET /api/foundation/v1/entity/list/users?email=john@example.com`
- `GET /api/foundation/v1/entity/list/users?name[like]=john`
- `GET /api/foundation/v1/entity/list/users?name[starts]=jo`
- `GET /api/foundation/v1/entity/list/users?email[ends]=@gmail.com`
- `GET /api/foundation/v1/entity/list/users?created_at[gte]=2025-01-01`
- `GET /api/foundation/v1/entity/list/users?status[in]=active,inactive`
- `GET /api/foundation/v1/entity/list/users?status[neq]=deleted`

### Sort examples
- `GET /api/foundation/v1/entity/list/users?sort=-created_at`
- `GET /api/foundation/v1/entity/list/users?sort=name,-created_at`

### Pagination examples
- `GET /api/foundation/v1/entity/list/users?offset=0&length=20`
- `GET /api/foundation/v1/entity/list/users?status=active&sort=-created_at&offset=0&length=20`

### Combined example
```text
GET /api/foundation/v1/entity/list/users?status=active&name[like]=john&created_at[gte]=2025-01-01&sort=name,-created_at&offset=0&length=20
```

## List Query Behavior
- `status=active` is treated as equality and maps to `status[eq]=active`.
- `like` performs a contains match using `%value%`.
- `starts` performs a prefix match using `value%`.
- `ends` performs a suffix match using `%value`.
- `in` accepts a comma-separated value list.
- Unknown fields are ignored and skipped.
- Unsupported operators are ignored and skipped.
- Sort direction defaults to ascending unless the field is prefixed with `-`.
- Sort can target declared main fields.
- Sort can also target declared meta fields when the meta field type is string-like.
- Meta sorting is currently limited to string-like field types such as `string`, `varchar`, `text`, and `char`.

## Store Flow
1. Resolve the target table from `{entity_name}` and confirm it exists.
2. Resolve the matching entity definition from the `entities` table by looking up the entity meta `table_name`.
3. Build main-field definitions from `entities.fields` and meta-field definitions from `entities.meta_fields`.
4. Build a writable main-column allowlist from the entity table plus the entity field definition.
5. Split request data into:
   - main table payload using only declared, writable main fields
   - explicit `meta` payload using only declared meta fields
6. Validate required and nullable rules from the entity definition before writing.
7. Normalize values by field definition type before insert.
8. Apply system defaults such as `uid`, `status`, `created_by`, `updated_by`, `created_at`, and `updated_at` when those columns exist.
9. Insert the main row inside a transaction.
10. Insert meta rows linked by `ref_parent` when a related meta table exists.
11. Return the created record in the standardized API response format.

## Update Flow
1. Resolve the target table from `{entity_name}` and confirm it exists.
2. Resolve the incoming `{data_uid}` against the table `uid` column when present, otherwise `id`.
3. Resolve the matching entity definition from the `entities` table by looking up the entity meta `table_name`.
4. Build main-field definitions and meta-field definitions from the entity definition.
5. Build a writable main-column allowlist from the entity table plus the entity field definition.
6. Split request data into:
   - main table payload using only declared, writable main fields
   - explicit `meta` payload using only declared meta fields
7. Validate required and nullable rules from the entity definition before writing.
8. Normalize values by field definition type before update.
9. Apply update audit defaults such as `updated_by` and `updated_at` when those columns exist.
10. Update the main row inside a transaction.
11. Upsert meta rows linked by `ref_parent`.
12. Return the updated record in the standardized API response format.

## Delete Flow
1. Resolve the target table from `{entity_name}` and confirm it exists.
2. Resolve the incoming `{data_uid}` against the table `uid` column when present, otherwise `id`.
3. Require a `status` column to support soft delete semantics.
4. Update the record status to `deleted`.
5. Fill `updated_by`, `updated_at`, `deleted_by`, and `deleted_at` when those columns exist.
6. Return the soft-deleted record in the standardized API response format.

## Type Handling
- Main-field and meta-field types are resolved from the entity definition, not trusted from the frontend payload.
- Integer-like fields are cast to integers.
- Decimal and float fields are cast to numeric values.
- Boolean fields accept standard boolean-like inputs.
- JSON fields accept arrays or valid JSON strings.
- Date and time fields are normalized with Carbon.
- `password` fields are hashed before insert.

## Error Handling
- Duplicate unique constraint violations return `409 Conflict`.
- Foreign key constraint violations return `409 Conflict`.
- Not-null constraint violations return `422 Unprocessable Entity`.
- Invalid request values return `400 Bad Request`.
- Missing target tables return `404 Not Found`.

## Meta Handling
- Only meta keys declared in `entities.meta_fields` are accepted for persistence.
- Explicit `meta` payload is supported and is the expected form submit shape.
- Meta records are stored using `ref_parent`, `meta_key`, and `meta_value`.

## Form Submit Contract
- Generic rendered forms submit to the dynamic entity API using the entity table name, for example `POST /api/foundation/v1/entity/store/test_entities`.
- The frontend payload shape is:

```json
{
  "title": "Example title",
  "summary": "Example summary",
  "meta": {
    "notes": "Example note"
  }
}
```

- Main table fields are sent as top-level keys.
- Meta fields are sent inside the `meta` object.
- The frontend uses the shared `apiClient` so submit requests follow the same auth and credential handling as dynamic table requests.
- When the API returns validation errors, the rendered form should surface them inline on matching fields and also show a short form-level summary for any generic or unmapped backend messages.
- A generic backend key such as `meta_value` is treated as a metadata validation failure on the frontend, and the UI highlights the submitted empty meta fields with a clearer user-facing message.
- The rendered form schema also supports layout spacer items inside `fields`.
- A spacer behaves like an empty responsive grid block, so `{ "id": "break-1", "type": "spacer" }` uses the default full-width spacer and pushes the next item to a new row.
- A spacer can also use responsive `size` values to behave like an empty layout column at larger breakpoints, for example `{ "id": "desktop-gap", "type": "spacer", "size": { "xs": 12, "lg": 2 } }`.
- A spacer may include `height`, for example `{ "id": "group-gap", "type": "spacer", "height": "0.75rem" }`, to add extra vertical gap between field groups.

## Defense In Depth
- The controller keeps a separate writable main-column guard in addition to entity-definition validation.
- This extra check intentionally limits writes to declared main-table columns only.
- A code comment marks this guard as removable later if entity-definition resolution becomes the only trusted validation source.
