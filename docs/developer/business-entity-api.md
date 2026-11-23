# Business Entity API

The business entity API reads and writes **one record across several tables**, as described by a business entity: the root, its linked parts, lists of rows, and references (including many-to-many pivots). Business entities are defined in Admin → Business Entities; see `business-entities.md`.

## Endpoints

| Method | URL |
|---|---|
| `GET` | `/api/foundation/v1/business-entity/list/{slug}` |
| `GET` | `/api/foundation/v1/business-entity/show/{slug}/{uid}` |
| `POST` | `/api/foundation/v1/business-entity/store/{slug}` |
| `PUT` | `/api/foundation/v1/business-entity/update/{slug}/{uid}` |
| `DELETE` | `/api/foundation/v1/business-entity/delete/{slug}/{uid}` |
| `GET` | `/api/foundation/v1/business-entity/options/{slug}/{alias}?search=&limit=` |

- `{uid}` is the root record's uid.
- Every endpoint needs a Sanctum token (or the web session).
- An unknown slug, an inactive business entity, or a part table that is no longer a registered entity table gives `404`.

## Permissions

**For now only platform admins (`super-admin`) can use business entities.** Since the Track B security changes (see `docs/track-b-security.md`), per-entity permissions (`entity.{table}.*`) grant nothing, until per-organisation rules replace them.

- The check still runs on **every table the record writes** (root, composed parts, lists of rows) and on referenced tables, so the rules can be widened again without changing callers.
- **The `users` table** is written through the user model (`EntityRepository`), so the model's password rules (row lock, revoking old sessions and tokens, audit) always run. That is why business entities may write users (`EntityPermission::allowsThroughModel`), while the generic entity API refuses `users` writes even for super-admin.

## Payload

- **Tree parts** (root, `has_one`, composed `belongs_to`, `morph_one`): flat keys `{alias}__{field}`, with meta under `meta`, as in the entity API.
- **Lists of rows** (`has_many`, `morph_many`): `"{alias}": [{"uid"?, field: value, "meta": {key: value}}]`.
- **References:** `"{alias}": "<uid>"` for one, or a list of uids for many. Tables without a `uid` (e.g. roles) use ids.

```json
{
  "customer__name": "Asha Roy",
  "meta": {"customer__phone": "9800000000"},
  "address__city": "Kolkata",
  "notes": [{"body": "First call"}, {"uid": "01J…", "body": "Edited note"}],
  "company": "01J…",
  "tags": ["01J…", "01J…"],
  "roles": ["3"]
}
```

A key that is not sent leaves that part unchanged.

**Store:**
- The root is always created. Any other part is created only if at least one of its fields is sent, or a list or reference that hangs from it.
- Link columns and morph types are filled in by the API, e.g. `addresses.customer_id`, `notes.customer_id`, `customers.company_id`, pivot rows.

**Update:**
- Partial: only the fields you send change, and a required field can't be blanked.
- A missing part is created when you send its fields.
- **A list replaces the current rows:** rows with a `uid` (or `id`) of this record are updated, rows without one are created, and current rows that are missing are removed. Removed rows are soft-deleted when the table has a `status` column, and deleted otherwise. A uid of another record's row is rejected.
- **A reference is set to what you send:** `null` or `[]` clears it. Pivot rows are synced, and existing ones are kept.

**Validation:**
- Each part is validated with its entity's field definitions (selected fields only), using the same rules as the entity API.
- All errors come back together as `422`, keyed `customer__name` or `meta.customer__phone`. List and reference errors are keyed by alias, e.g. `"notes": ["Row 2: The body field is required."]`.
- A reference must be a row the user can see (not deleted, in scope).

**Writes** run in one transaction, in link order: `belongs_to` parts first, then the part pointing at them, then `has_one` parts, then lists of rows and pivots. If any part fails, nothing is saved. `Events\BusinessEntityRecordSaved` is dispatched afterwards.

## Response

```json
{
  "id": 7, "uid": "01J…", "status": "active", "created_at": "…", "updated_at": "…",
  "detail_summary": {"user_name": "Admin", "display_datetime": "…"},
  "customer__name": "Asha Roy",
  "address__city": "Kolkata",
  "company__name": "Iquesters",
  "meta": [{"meta_key": "customer__phone", "meta_value": "9800000000"}],
  "notes": [{"id": 4, "uid": "01J…", "body": "First call", "meta": {}}],
  "company": "01J…",
  "tags": ["01J…"],
  "labels": {"company": "Acme", "tags": ["Gold"]},
  "parts": {"customer": {"id": 7, "uid": "01J…"}, "address": {"id": 3, "uid": "01J…"}, "company": null}
}
```

- `id`, `uid`, `status` and the timestamps are the root's.
- `labels` gives each reference in words, from its label column (one name, or a list for many-to-many). Table components show them, e.g. a user's roles as badges.
- A missing part gives `null` values.
- Sensitive fields are redacted, as in the entity API.

## List

`offset`, `length` (max 100), `search`, `sort` and filters use the same syntax as the entity API. Filters and sort apply to the **root part's** selected fields (`customer__name`) and its system columns (`status`, `created_at`, …):

```
?customer__name[like]=asha&sort=-created_at
```

Filtering on another part's field returns `400`.

## Options

`options/{slug}/{alias}` lists what a reference can point at, as `[{"value": "<uid or id>", "label": "<label column>"}]`.
- Up to 200 items, filtered with `?search=` on the label column.
- Only rows that are not deleted and in scope.
- The generated form's selects and checkboxes load it (`optionsFrom.url`).

## Delete

Soft-deletes the root and what it **owns**: `has_one` / `morph_one` chains from the root that have a `status` column, and their lists of rows. Shared data is not changed: `belongs_to` parts (such as a company), referenced records and pivot rows.

## UI

The generated schemas use `"entity": "business-entity/{slug}"`:

- **Table:** loads rows from `business-entity/list/{slug}` (`entityRecordUrl()` in user-interface `api-client.js`).
- **Form:** loads the record from its `dataEndpoint`.

No page code is needed: `/list/{table schema uid}` shows the table with Create, split view, edit and delete.
