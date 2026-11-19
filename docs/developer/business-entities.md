# Business entities

A **business entity** is one record made of several entity tables, for example a *customer* with its *address* and its *company*. You build it from entities that are already published, and the platform gives you one form, one table and one API for the combined record.

- **Definition:** foundation keeps it (`business_entities`, `BusinessEntityDefinition`) and builds the form and table schemas.
- **Records:** the business entity API reads and writes them, at `/api/foundation/v1/business-entity/...` (see `business-entity-api.md`).

## Building one from the admin UI

**Admin → Business Entities → + Business entity** (`/business-entity`, admins only).

1. Give it a **name**. The slug used in API URLs comes from the name and never changes.
2. **Root part:** choose its entity. A record's uid is the root row's uid.
3. **Add part** for each linked table:
   - choose its entity and an **alias** (lowercase, e.g. `address`);
   - choose its **link** to an earlier part:

     | Link | Who holds the column | Example | On delete |
     |---|---|---|---|
     | `has_one` | this part's table | `addresses.customer_id` → customer | deleted with the record (owned) |
     | `belongs_to` | the other part's table | `customers.company_id` → company | kept (shared) |

4. Tick the **fields** and **meta fields** the form should have. Link columns are filled in automatically, so you can't select them.
5. **Create** or **Save**. This validates the definition and creates or updates its form schema (`business-{slug}-form`) and table schema (`business-{slug}-table`).

**Records** on the list or edit page opens the generated table (`/list/{table schema uid}`). It has a Create button, row click opens split view, and the form handles view, edit and delete.

Only **published** entities can be used: their table must exist. Unpublished ones are listed but disabled.

## Definition format

Stored in `business_entities.definition`:

```json
{
  "version": 1,
  "parts": [
    {"alias": "customer", "entity_uid": "01J…", "table": "customers",
     "fields": [{"field": "name"}, {"field": "email"}],
     "meta_fields": [{"meta_key": "phone"}]},
    {"alias": "address", "entity_uid": "01J…", "table": "addresses",
     "link": {"type": "has_one", "to": "customer", "foreign_key": "customer_id"},
     "fields": [{"field": "city"}]},
    {"alias": "company", "entity_uid": "01J…", "table": "companies",
     "link": {"type": "belongs_to", "to": "customer", "foreign_key": "company_id"},
     "fields": [{"field": "name"}]}
  ]
}
```

Rules, checked by `BusinessEntityDefinition` (structure) and `BusinessEntityDefinitionValidator` (against the database):

- **Parts:**
  - the first part is the root and has no link;
  - every other part links to an earlier part;
  - aliases are unique and match `^[a-z][a-z0-9]*(_[a-z0-9]+)*$` (no `__`).
- **Entities and tables:**
  - each `entity_uid` is an existing, not-deleted entity whose table exists;
  - `table` is always taken from the entity, so a submitted value is ignored.
- **Fields:**
  - selected fields and meta keys must be defined on the entity;
  - system columns (`id`, `uid`, `status`, `created_*`, `updated_*`, `deleted_*`) and link columns can't be selected;
  - at least one field must be selected.
- **Links:** each link column must exist in the table that holds it.

Fields are addressed as **`{alias}__{field}`** (e.g. `address__city`) in forms, payloads and responses. The keys stay flat because the form engine uses field ids as payload keys, and PHP turns a `.` in form-data names into `_`.

## In code

```php
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;

$definition = app(BusinessEntityDefinitionValidator::class)->validate($array); // throws InvalidArgumentException

$businessEntity = BusinessEntity::create([
    'uid' => (string) Str::ulid(),
    'name' => 'Customer Profile',
    'slug' => 'customer-profile',
    'definition' => $definition->toArray(),
    'status' => 'active',
]);

app(BusinessEntitySchemaGenerator::class)->sync($businessEntity); // needs iquesters/user-interface
```

`BusinessEntityDefinition` also gives you:

| Method | Returns |
|---|---|
| `writeOrder()` | the order the parts are saved in |
| `foreignKeys($alias)` | the link columns in that part's table |
| `fields()` | every selected field with its flat key |

## Schema generation

`FormSchemaGenerator::build()` and `TableSchemaGenerator::build()` are the generic halves of `generate()`: they take a display name, a schema `entity` key and endpoints instead of an `Entity`. `BusinessEntitySchemaGenerator` uses them.

**Form schema:**
- the `entity` key is `business-entity/{slug}`;
- the mode endpoints and `dataEndpoint` point at `/api/foundation/v1/business-entity/...`;
- labels are prefixed with the part (e.g. "Address City") when there is more than one part.

**Table schema:**
- the same `entity` key, which the UI table lists from the business entity API;
- columns: id, the first two selected fields, status;
- a `create` button.

## Limits (version 1)

- **Links:** only one-to-one (`has_one` / `belongs_to`). Lists (`has_many`, many-to-many) need repeatable form groups and are not supported yet.
- **List filters and sorting** work on the root part's fields only.
- **Deleting a business entity** only soft-deletes its definition; records in the part tables are not touched.
