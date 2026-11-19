# Business entities

A **business entity** is one record made of several existing tables, for example a *customer* with:
- its *address* (has one);
- its *notes* (has many);
- the *company* it belongs to (a reference);
- its *tags* and *roles* (many-to-many).

You build it from published entities and platform models. The platform then gives you one form, one table and one API for the combined record.

**It is configuration only.** A business entity describes how tables that already exist are related, and nothing else:
- the data stays in the parts' own tables, link columns and pivot tables;
- saving a definition never creates or changes tables;
- every table and column a link uses must already exist. A missing pivot table is created as an entity (Entity → Create, then Publish), not by the business entity.

| Piece | Where |
|---|---|
| Definition | `business_entities`, `BusinessEntityDefinition` (structure), `BusinessEntityDefinitionValidator` (against the database) |
| Records | `BusinessEntityRecords`, at `/api/foundation/v1/business-entity/...` (see `business-entity-api.md`) |
| Saving definitions | `BusinessEntityService` (admin builder and `business-entity:sync`) |
| Form and table schemas | `BusinessEntitySchemaGenerator` (needs iquesters/user-interface) |

## Building one from the admin UI

**Admin → Business Entities → + Business entity** (`/business-entity`, admins only).

1. Give it a **name**. The slug used in API URLs comes from the name and never changes.
2. **Add entity:** the picker lists published entities and, after the first one, platform models. Entities already added are disabled.
   - The first entity you add is the **primary** one (the root). A record's uid is its uid.
   - Tick *Primary entity* in the picker, or use the crown button on a part, to change it.
3. For every other entity, choose:
   - its **relation** to an earlier part. Each part shows the relation as a sentence, e.g. "Each Customer has many Notes, added as rows in the form";
   - **How**, when the relation allows both: *Fill in on the form* (compose) or *Pick an existing record* (reference);
   - the **link columns**. Common ones are suggested, e.g. `customer_id`. For a many-to-many link, the **pivot table** and its columns. A missing pivot shows **Needs pivot table** with a *Create pivot entity* button;
   - the **fields** to show. Link columns are filled in automatically, so they can't be selected.
4. **Review & create / save:** the server checks the whole definition against the database. You then see the structure at a glance: each part, its relation in words, where the link is stored, and its fields. **Confirm & save** stores it and generates the form schema (`business-{slug}-form`) and the table schema (`business-{slug}-table`).

**Records** on the list or edit page opens the generated table (`/list/{table schema uid}`). It has a Create button; clicking a row opens split view, and the form handles view, edit and delete.

## Relations

Every non-root part links **to** an earlier part, its "parent". The parent must be the root, or a part that is one composed row.

| Type | Laravel | Stored in | Modes | In the form |
|---|---|---|---|---|
| `has_one` | `hasOne` | this part's `foreign_key` → parent id | compose | its fields, inline |
| `morph_one` | `morphOne` | this part's `foreign_key` + `morph_type` | compose | its fields, inline |
| `belongs_to` | `belongsTo` | the parent's `foreign_key` → this part's id | compose, reference | its fields inline, or a select |
| `has_many` | `hasMany` | this part's `foreign_key` → parent id | compose | a repeatable group of rows |
| `morph_many` | `morphMany` | this part's `foreign_key` + `morph_type` | compose | a repeatable group of rows |
| `belongs_to_many` | `belongsToMany` | pivot `foreign_pivot_key` → parent, `related_pivot_key` → this part | reference | checkboxes |
| `morph_to_many` | `morphToMany` | the same, plus the pivot `morph_type` naming the parent | reference | checkboxes |
| `morphed_by_many` | `morphedByMany` | the same, plus the pivot `morph_type` naming this part | reference | checkboxes |

**Modes:**
- **compose:** the rows are created and edited inside the record.
- **reference:** the record points at rows that already exist. They are picked in the form, and the part has no fields of its own.

**Morph type value:** `morph_class`. It defaults to the morph class of the side it describes:
- a model part: the model's `getMorphClass()`;
- an entity whose table belongs to a registered model (e.g. the `users` entity): that model's morph class, `App\Models\User`, as Spatie stores it;
- any other entity: its table name.

Set it when the existing rows use something else.

**Not offered,** because there is nothing to store for them:
- `has_one_through` and `has_many_through` are read paths: chain two links instead;
- `morph_to` targets a different table per row, so it has no fixed fields.

**Lists and references are leaves:** no other part can link to them.

### Platform models

Model parts point at platform models, such as users, roles and permissions, through their own tables and pivots, e.g. Spatie's `model_has_roles`:

```json
{"alias": "roles", "model": "role",
 "link": {"type": "morph_to_many", "to": "staff",
          "pivot": {"table": "model_has_roles", "foreign_pivot_key": "model_id",
                    "related_pivot_key": "role_id", "morph_type": "model_type"}}}
```

- Model parts are **reference only**: a business record never creates or edits users or roles, because they have their own screens and rules.
- The root must be an entity.
- Foundation registers `user`, plus `role` and `permission` when spatie/laravel-permission is installed. Modules add theirs in `boot()`:

```php
app(\Iquesters\Foundation\Support\BusinessEntity\BusinessEntityModels::class)
    ->register('organisation', Organisation::class, 'Organisations');
```

### Adding a relation type

A relation type describes its shape: where the link is stored (`self`, `parent` or `pivot`), its morph side, one or many rows, and its modes. The record engine runs it from that description. Register one by binding it with the `business-entity.relation-types` tag:

```php
use Iquesters\Foundation\Support\BusinessEntity\Relations\{RelationTypes, StandardRelationType};

$this->app->bind('my-module.has-many-lines', fn () =>
    new StandardRelationType('has_many_lines', 'has many lines', 'self', 'many', ['compose']));
$this->app->tag(['my-module.has-many-lines'], RelationTypes::TAG);
```

Combinations the engine can't store are rejected, e.g. a composed pivot.

## Definition format (version 2)

Stored in `business_entities.definition`. Version 1 definitions (has_one / belongs_to only) are read unchanged.

```json
{
  "version": 2,
  "parts": [
    {"alias": "customer", "entity_uid": "01J…", "table": "customers",
     "fields": [{"field": "name"}], "meta_fields": [{"meta_key": "phone"}]},
    {"alias": "address", "entity_uid": "01J…", "table": "addresses",
     "link": {"type": "has_one", "to": "customer", "mode": "compose", "foreign_key": "customer_id"},
     "fields": [{"field": "city"}]},
    {"alias": "notes", "entity_uid": "01J…", "table": "notes",
     "link": {"type": "has_many", "to": "customer", "mode": "compose", "foreign_key": "customer_id"},
     "fields": [{"field": "body"}]},
    {"alias": "company", "entity_uid": "01J…", "table": "companies",
     "link": {"type": "belongs_to", "to": "customer", "mode": "reference",
              "foreign_key": "company_id", "label_column": "name"}},
    {"alias": "tags", "entity_uid": "01J…", "table": "tags",
     "link": {"type": "belongs_to_many", "to": "customer", "mode": "reference",
              "pivot": {"table": "customer_tag", "foreign_pivot_key": "customer_id", "related_pivot_key": "tag_id"},
              "label_column": "name"}}
  ]
}
```

**Structure rules** (`BusinessEntityDefinition`):
- **Parts:**
  - the first part is the root: an entity, with no link;
  - every other part links to an earlier tree part (the root or a composed one-row part);
  - aliases are unique and match `^[a-z][a-z0-9]*(_[a-z0-9]+)*$`;
  - reserved aliases: `id`, `uid`, `status`, `meta`, `parts`, the audit columns.
- **Source:** `entity_uid`, or `entity` (the table name, used by definition files), or `model`. A model can only be referenced.
- **Fields:**
  - a reference has no fields;
  - system columns (`id`, `uid`, `status`, `created_*`, `updated_*`, `deleted_*`) and columns filled in by links can't be selected;
  - at least one field or relation is needed.

**Database rules** (`BusinessEntityDefinitionValidator`):
- each entity exists and is published;
- the table always comes from the entity (or the model), never from the submitted definition;
- every link column, morph type column, pivot table and pivot column exists. A missing pivot is listed in `InvalidBusinessEntityDefinition::$missingPivots`;
- referenced tables have an `id`;
- `label_column` defaults to the first of `name`, `title`, `label`, `display_name`, `email`, `code`, `uid`, `id`.

## Definition files

Definitions can live in version control and be deployed like migrations:

```bash
php artisan business-entity:export customer-account   # → database/business-entities/customer-account.json
php artisan business-entity:sync                       # create or update every file (matched by slug)
php artisan business-entity:sync --dry-run             # validate only
```

**Files:**
- they name entities by **table** (`"entity": "customers"`), because uids differ per install;
- a sync validates each file like an admin save and regenerates the schemas;
- it never changes the database structure;
- the command fails if any file is invalid, and the valid files are still applied.

## Events

| Event | When |
|---|---|
| `Events\BusinessEntityDefinitionChanged` (`businessEntity`, `action`: created/updated/deleted) | a definition was saved or deleted, from the builder or a sync |
| `Events\BusinessEntityRecordSaved` (`definition`, `record`, `action`: created/updated/deleted) | a record was written through the business entity API (after commit) |

## In code

```php
use Iquesters\Foundation\Services\BusinessEntity\BusinessEntityService;

$service = app(BusinessEntityService::class);
$definition = $service->validate($array);            // throws InvalidArgumentException
$businessEntity = $service->create(['name' => 'Customer Account'], $array);
$service->update($businessEntity, ['name' => 'Customer Account'], $array);
```

`BusinessEntityDefinition` also gives you:

| Method | Returns |
|---|---|
| `treeAliases()` / `leafAliases()` | parts with flat fields / parts sent under their alias (lists and references) |
| `isRows($alias)` / `isReference($alias)` | the part's kind |
| `writeOrder()` | the order tree parts are saved in |
| `foreignKeys($alias)`, `morphColumns($alias)`, `lockedColumns($alias)` | the columns links fill in |
| `fields()` | every flat field with its key |
| `toPortableArray()` | the definition with entities named by table |

## Schema generation

`FormSchemaGenerator::build()` and `TableSchemaGenerator::build()` are the generic halves of `generate()`. `BusinessEntitySchemaGenerator` uses them, and both entities and business entities store their schemas through `Services\Schema\GeneratedSchemaStore`.

**Form schema:**
- the `entity` key is `business-entity/{slug}`;
- the endpoints and `dataEndpoint` point at `/api/foundation/v1/business-entity/...`;
- tree fields are labelled with their part (e.g. "Address City") when there is more than one part;
- a list of rows is a `"type": "repeater"` field whose `fields` are the part's columns;
- a reference is a `select` (one) or `checkbox` (many) with `"optionsFrom": {"url": ".../options/{slug}/{alias}"}`.

**Table schema:**
- the same `entity` key;
- columns: id, the first two selected fields, status;
- a `create` button.

## Limits

- **List filters and sorting** work on the root part's fields only.
- **Pivots:** extra pivot columns (other than timestamps) are not edited.
- **Deleting a business entity** only soft-deletes its definition; records in the part tables are not touched.
