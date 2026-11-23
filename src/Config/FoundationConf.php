<?php

namespace Iquesters\Foundation\Config;

use Iquesters\Foundation\Enums\Module;
use Iquesters\Foundation\Support\BaseConf;

/**
 * Foundation's DB-backed settings (see docs/developer/configuration.md).
 * Its API is declared in composer.json (extra.platform.api); deploy-time API
 * limits are in config/foundation.php.
 */
class FoundationConf extends BaseConf
{
    protected ?string $identifier = Module::FOUNDATION;

    /**
     * Deprecated compatibility setting; cannot grant platform authority.
     * EnsureAdmin admits only super-admin. Tenant admin uses explicit roles.
     */
    protected string $admin_roles;

    protected function prepareDefault(BaseConf $default_values)
    {
        $default_values->admin_roles = 'super-admin';
    }

    protected function rules(): array
    {
        return ['admin_roles' => 'string|max:500'];
    }
}
