# Navigation

One navigation tree drives the **sidebar**, the **page tabs** and the **breadcrumbs**.

| Level | What it is | Shown as |
|---|---|---|
| Module | a row in `modules` (order: Admin → Navigations) | module bar / minibar |
| Item | `navigation_items` row without a parent | sidebar entry |
| Tab | `navigation_items` row whose parent is an item | tab on that item's pages |

Breadcrumbs are built from the same tree: `Module › Item › Tab › page`. A page adds its own last crumb, e.g. a record's name:

```blade
@section('breadcrumb', $organisation->name)
```

On phones, breadcrumbs show as a "‹ back" link to the previous crumb.

## Data

**`navigation_items`:**
- `ref_module`, `ref_parent` (a tab's item), `key` (stable identity);
- `label`, `icon`, `sort`, `status`;
- target: `route` + `params` (a `null` param takes the current page's value), or `table_schema_uid` (its list page), or `url`;
- `permission`: only users with it see the item;
- `source` (`module`, `admin` or `system`) and `customized` (an admin changed a module item).

**`navigation_item_metas`:** extra settings per item (`getMeta()` / `setMeta()`), e.g. a badge or a breadcrumb label of its own.

## Declaring items in a module

The seeder's `module_sidebar_menu` declares items. An item may list `children` (its tabs) and `meta`:

```php
['key' => 'jobs', 'label' => 'Jobs', 'icon' => 'fas fa-sync-alt', 'route' => 'jobs.index',
 'permission' => 'view-jobs',
 'children' => [
     ['label' => 'Current', 'route' => 'jobs.index'],
     ['label' => 'Failed', 'route' => 'jobs.failed'],
 ]],
```

Seeding (or `php artisan navigation:sync`) copies the declaration into `navigation_items` (`Services\Navigation\NavigationSync`):
- **New items** are created.
- **Items the module still owns** are updated.
- **Items an admin changed** (`customized`) are kept as they are.
- **Items the module no longer declares** are hidden; admin items are never touched.

## Fixed items (dashboard and own-page tab)

`NavigationSync::ensureSystemItems()` adds, once, `source = system` items that admins can **move but not edit, hide or delete**:
- **`{module}.dashboard`:** the module's dashboard (`/dashboard/{module}`, route `module.dashboard`), first in its sidebar. By default it lists the module's pages; a module shows its own view by setting the module meta `module_dashboard_view` (the view gets `$module` and `$items`). The breadcrumbs' module crumb links to it.
- **`{item}.self`:** under every sidebar item with tabs, the item's own page as a tab (first, unless moved). The sidebar item always opens its own page, wherever this tab is in the order. It isn't added when a tab already opens the item's page (e.g. Jobs → "Current").

## Editing (Admin → Navigation)

`/navigation-items`, admins only:
- add sidebar items and tabs pointing at a table, a module's page (route) or a link;
- rename them, change their icon or who sees them;
- drag to reorder;
- hide them.

**Changing a module's item** marks it customized, so module updates keep the change. "Restore" gives it back to the module. Admin-added items can be deleted; module items can only be hidden, because they would come back on the next sync otherwise.

## Older pages

A view's own `$tabs = [...]` array still works but is deprecated: declare tabs as `children` instead. Labels are always printed as text.
