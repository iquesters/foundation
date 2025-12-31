<?php

/**
 * Namespace resolver for Iquesters modules.
 * Generates full namespaces for modules like Iquesters\Foundation\Config, etc.
 */

namespace Iquesters\Foundation\System\Package;

use Illuminate\Support\Str;

class NamespaceResolver
{
    protected const DEFAULT_VENDOR_NAME = 'iquesters';

    // Folder constants - UPPER_SNAKE_CASE with hierarchy
    protected const CONFIG_FOLDER_NAME         = 'Config';
    protected const CONSOLE_FOLDER_NAME        = 'Console';
    protected const CONSTANTS_FOLDER_NAME      = 'Constants';
    protected const DATABASE_FOLDER_NAME       = 'Database';
    protected const DATABASE_SEEDERS_FOLDER_NAME = 'Seeders';
    protected const ENUMS_FOLDER_NAME          = 'Enums';
    protected const HTTP_FOLDER_NAME           = 'Http';
    protected const HTTP_CONTROLLERS_FOLDER_NAME = 'Controllers';
    protected const HTTP_MIDDLEWARE_FOLDER_NAME  = 'Middleware';
    protected const MODELS_FOLDER_NAME         = 'Models';
    protected const OPENAPI_FOLDER_NAME        = 'OpenApi';
    protected const PACKAGE_FOLDER_NAME        = 'Package';
    protected const PROVIDERS_FOLDER_NAME      = 'Providers';
    protected const ROUTING_FOLDER_NAME        = 'Routing';
    protected const SUPPORT_FOLDER_NAME        = 'Support';
    protected const SYSTEM_FOLDER_NAME         = 'System';
    protected const UTILS_FOLDER_NAME          = 'Utils';

    protected string $vendorName;
    protected string $moduleName;


    /**
     * @param string $moduleName Module name (e.g., 'foundation')
     * @param string|null $vendorName Custom vendor name (defaults to DEFAULT_VENDOR_NAME)
     */
    public function __construct(string $moduleName, ?string $vendorName = null)
    {
        $this->moduleName = $moduleName;
        $this->vendorName = $vendorName ?? self::DEFAULT_VENDOR_NAME;
    }

    /**
     * Get base module namespace: Vendor\Module
     * Example: Iquesters\Foundation
     */
    public function getNamespace(): string
    {
        return "{$this->getVendorNamespace()}\\{$this->getModuleNamespace()}";
    }

    /**
     * Get Config namespace: Vendor\Module\Config
     */
    public function getConfigNamespace(): string
    {
        return $this->append(self::CONFIG_FOLDER_NAME);
    }

    /**
     * Get Console namespace: Vendor\Module\Console
     */
    public function getConsoleNamespace(): string
    {
        return $this->append(self::CONSOLE_FOLDER_NAME);
    }

    /**
     * Get Constants namespace: Vendor\Module\Constants
     */
    public function getConstantsNamespace(): string
    {
        return $this->append(self::CONSTANTS_FOLDER_NAME);
    }

    /**
     * Get Database namespace: Vendor\Module\Database
     */
    public function getDatabaseNamespace(): string
    {
        return $this->append(self::DATABASE_FOLDER_NAME);
    }

    /**
     * Get Database Seeders namespace: Vendor\Module\Database\Seeders
     */
    public function getSeedersNamespace(): string
    {
        return $this->append(self::DATABASE_FOLDER_NAME) . '\\' . self::DATABASE_SEEDERS_FOLDER_NAME;
    }

    /**
     * Get Enums namespace: Vendor\Module\Enums
     */
    public function getEnumsNamespace(): string
    {
        return $this->append(self::ENUMS_FOLDER_NAME);
    }

    /**
     * Get Http namespace: Vendor\Module\Http
     */
    public function getHttpNamespace(): string
    {
        return $this->append(self::HTTP_FOLDER_NAME);
    }

    /**
     * Get Controllers namespace: Vendor\Module\Http\Controllers
     */
    public function getControllersNamespace(): string
    {
        return $this->append(self::HTTP_FOLDER_NAME) . '\\' . self::HTTP_CONTROLLERS_FOLDER_NAME;
    }

    /**
     * Get Middleware namespace: Vendor\Module\Http\Middleware
     */
    public function getMiddlewareNamespace(): string
    {
        return $this->append(self::HTTP_FOLDER_NAME) . '\\' . self::HTTP_MIDDLEWARE_FOLDER_NAME;
    }

    /**
     * Get Models namespace: Vendor\Module\Models
     */
    public function getModelsNamespace(): string
    {
        return $this->append(self::MODELS_FOLDER_NAME);
    }

    /**
     * Get OpenApi namespace: Vendor\Module\OpenApi
     */
    public function getOpenApiNamespace(): string
    {
        return $this->append(self::OPENAPI_FOLDER_NAME);
    }

    /**
     * Get Package namespace: Vendor\Module\Package
     */
    public function getPackageNamespace(): string
    {
        return $this->append(self::PACKAGE_FOLDER_NAME);
    }

    /**
     * Get Providers namespace: Vendor\Module\Providers
     */
    public function getProvidersNamespace(): string
    {
        return $this->append(self::PROVIDERS_FOLDER_NAME);
    }

    /**
     * Get Routing namespace: Vendor\Module\Routing
     */
    public function getRoutingNamespace(): string
    {
        return $this->append(self::ROUTING_FOLDER_NAME);
    }

    /**
     * Get Support namespace: Vendor\Module\Support
     */
    public function getSupportNamespace(): string
    {
        return $this->append(self::SUPPORT_FOLDER_NAME);
    }

    /**
     * Get System namespace: Vendor\Module\System
     */
    public function getSystemNamespace(): string
    {
        return $this->append(self::SYSTEM_FOLDER_NAME);
    }

    /**
     * Get Utils namespace: Vendor\Module\Utils
     */
    public function getUtilsNamespace(): string
    {
        return $this->append(self::UTILS_FOLDER_NAME);
    }

    /**
     * Append folder segment to base namespace
     */
    public function append(string $segment): string
    {
        return $this->getNamespace() . '\\' . Str::studly($segment);
    }

    /**
     * Get studly cased vendor name
     */
    public function getVendorNamespace(): string
    {
        return Str::studly($this->vendorName);
    }

    /**
     * Get studly cased module name
     */
    public function getModuleNamespace(): string
    {
        return Str::studly($this->moduleName);
    }
}