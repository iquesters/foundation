# Configuration (Developer)

## How module settings work

Every module has one **settings class** (`src/Config/{Module}Conf.php`, extends `BaseConf`):

```php
class UserManagementConf extends BaseConf
{
    protected ?string $identifier = Module::USER_MGMT;

    protected string $auth_form_type;            // typed setting
    protected RecaptchaConf $recaptcha;          // nested group (its own class)
    /** @var OAuthConf[] */
    protected array $o_auth_providers;           // map of groups, keyed (e.g. "google")

    protected function prepareDefault(BaseConf $d)
    {
        $d->auth_form_type = 'classic';          // defaults live in code
    }

    protected function rules(): array
    {
        return ['auth_form_type' => 'in:classic,schema,both'];   // Laravel validation rules
    }
}
```

- **Reading:** `ConfProvider::from(Module::USER_MGMT)->auth_form_type` (one instance per request; `isset()` / `??` work).
- **Storage:** table `module_settings`, one JSON document per module holding **only values that differ from the
  defaults**. Defaults always come from `prepareDefault()`, so a new default in an update applies automatically, and
  seeders never touch settings.
- **Types:** stored values are converted to the declared type (bool, int, float, string, nullable, list, nested group,
  map of groups). A value that can't be converted, or breaks a rule, is logged and the **default** is used.
- **Writing:** `ConfProvider::from($module)->update(['recaptcha' => ['enabled' => true]], $userId)` validates (types +
  rules) and throws `ValidationException` on bad input; nothing is saved then.
- **Secrets:** keys containing `secret`, `token`, `password`, `api_key` are encrypted at rest (`enc:`), decrypted in memory.
- **Admin screen:** `/modules/config/{module}` shows every setting with its default, typed inputs and *Reset to default*.
- **Upgrading from 1.x:** migration `create_module_settings_table` converts the old `master_data_metas` rows (keeping
  only non-default values); the old rows are left untouched.

Foundation has two kinds of settings.

## DB-backed conf (runtime, editable)

Read through `ConfProvider::from(Module::FOUNDATION)`; defaults in `src/Config/FoundationConf.php`,
written to `master_data` / `master_data_metas` by `php artisan foundation:seed`.

| Key | DB meta key | Default | Purpose |
|---|---|---|---|
| `admin_roles` | `ADMIN_ROLES` | `super-admin` | Deprecated compatibility setting. It cannot grant platform authority; `ensure.admin` and the transitional generic entity API accept only the web-guard `super-admin`. Tenant administration uses explicit scoped assignments. |

`admin_roles` no longer changes authorization. Existing stored values are retained without granting platform access; see `docs/track-b-security.md`. The web-guard `super-admin` role is the sole platform authority
out. An empty value falls back to the default. Use `EnsureAdmin::roles()` to get the effective list.

## `config/foundation.php` (deploy-time)

Settings read while routes are registered, so they can't come from the DB (they would be frozen into
`route:cache`). Merged by `FoundationServiceProvider`; publish the file to override.

| Key | Purpose |
|---|---|
| `api.middleware.{module}` | Extra middleware for every version of a module's API |
| `api.rate_limits.{module}.{version}` | `"requests,minutes"` throttle per module API version |

See [api.md](api.md) for module API routing.

## Module public assets

A module's `public/` files are handled by a subclass of `Support\ModuleAssets` that declares its slug
(e.g. `Iquesters\UserInterface\Support\Assets` → `userinterface`):

- `Assets::registerRoutes()` in the provider's `boot()`: `/module-assets/{slug}/{path}` (+ legacy `/vendor/{slug}/{path}`),
  serving only files inside `public/`.
- `Assets::url('js/app.js')`: the published copy in `public/vendor/{slug}` when current, else the route.
- `Assets::resolveUrl($value)`: a configured asset value (URL, absolute path, app file, `pkg::file`) → URL.
- Publish `public/` under the `laravel-assets` tag so `composer update` refreshes the published copy.
