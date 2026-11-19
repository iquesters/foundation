# Business Entity API

The business entity API reads and writes **one record across several entity tables**, as described by a business entity. Business entities are defined in Admin → Business Entities; see `business-entities.md`.

## Endpoints

| Method | URL |
|---|---|
| `GET` | `/api/foundation/v1/business-entity/list/{slug}` |
| `GET` | `/api/foundation/v1/business-entity/show/{slug}/{uid}` |
| `POST` | `/api/foundation/v1/business-entity/store/{slug}` |
| `PUT` | `/api/foundation/v1/business-entity/update/{slug}/{uid}` |
| `DELETE` | `/api/foundation/v1/business-entity/delete/{slug}/{uid}` |

- `{uid}` is the root record's uid.
- Every endpoint needs a Sanctum token (or the web session).
- An unknown slug, an inactive business entity, or a part table that is no longer a registered entity table gives `404`.

## Permissions

Each action needs `entity.{table}.{view|create|update|delete}` on **every** part's table. Admin roles always pass. When an update creates a part that didn't exist yet, it also needs `create` on that part's table.

## Payload

Fields use flat keys `{alias}__{field}`. Meta fields go under `meta`, as in the entity API:

```json
{
  "customer__name": "Asha Roy",
  "customer__email": "asha@example.test",
  "meta": {"customer__phone": "9800000000"},
  "address__city": "Kolkata",
  "company__name": "Iquesters"
}
```

**Store:**
- The root is always created. Any other part is created only if at least one of its fields is sent.
- Link columns are filled in by the API, e.g. `addresses.customer_id` and `customers.company_id`.

**Update:**
- Partial: only the fields you send change, and a required field can't be blanked.
- A missing part is created when you send its fields.

**Validation:**
- Each part is validated with its entity's field definitions (selected fields only), using the same rules as the entity API.
- All errors come back together as `422`, keyed `customer__name` or `meta.customer__phone`.

**Writes** run in one transaction, in link order: `belongs_to` parts first, then the part pointing at them, then `has_one` parts. If any part fails, nothing is saved.

## Response

```json
{
  "id": 7, "uid": "01J…", "status": "active", "created_at": "…", "updated_at": "…",
  "detail_summary": {"user_name": "Admin", "display_datetime": "…"},
  "customer__name": "Asha Roy",
  "address__city": "Kolkata",
  "company__name": "Iquesters",
  "meta": [{"meta_key": "customer__phone", "meta_value": "9800000000"}],
  "parts": {"customer": {"id": 7, "uid": "01J…"}, "address": {"id": 3, "uid": "01J…"}, "company": null}
}
```

- `id`, `uid`, `status` and the timestamps are the root's.
- A missing part gives `null` values.
- Sensitive fields are redacted, as in the entity API.

## List

`offset`, `length` (max 100), `search`, `sort` and filters use the same syntax as the entity API. Filters and sort apply to the **root part's** selected fields (`customer__name`) and its system columns (`status`, `created_at`, …):

```
?customer__name[like]=asha&sort=-created_at
```

Filtering on another part's field returns `400`.

## Delete

Soft-deletes the root and the parts it **owns**: `has_one` chains from the root that have a `status` column. `belongs_to` parts, such as a shared company, are not changed.

## UI

The generated schemas use `"entity": "business-entity/{slug}"`:

- **Table:** loads rows from `business-entity/list/{slug}` (`entityRecordUrl()` in user-interface `api-client.js`).
- **Form:** loads the record from its `dataEndpoint`.

No page code is needed: `/list/{table schema uid}` shows the table with Create, split view, edit and delete.
