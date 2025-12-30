<?php

namespace Iquesters\Foundation\System\Package;

use Iquesters\Foundation\System\Package\NamespaceResolver;
use Illuminate\Support\Str;

abstract class PackageInfo
{
    /* -------------------------------------------------
     | Constants
     |--------------------------------------------------*/
    private const VENDOR_NAME = 'iquesters';

    protected const ROUTES_WEB_PATH       = 'routes/web.php';
    protected const MIGRATIONS_PATH       = 'database/migrations';
    protected const VIEWS_PATH            = 'resources/views';
    protected const CONFIG_DIR            = 'config';
    protected const DIRECTORY_SEPARATOR   = '/';

    /* -------------------------------------------------
     | Properties
     |--------------------------------------------------*/
    protected string $module_name = '';

    // Default paths
    protected string $default_migrations_path;
    protected string $default_views_path;
    protected string $default_config_path;
    protected string $default_routes_path;

    // Custom paths (optional overrides)
    protected ?string $custom_migrations_path = null;
    protected ?string $custom_views_path = null;
    protected ?string $custom_config_path = null;
    protected ?string $custom_routes_path = null;

    // Specific classes only
    protected ?array $specific_providers = null;
    protected ?array $specific_commands = null;
    protected ?array $specific_middlewares = null;
    protected ?array $specific_models = null;

    // Common properties
    protected ?string $package_name = null;
    protected ?string $conf_name = null;
    protected bool $load_laravel_native_config = false;
    protected ?string $seeder_class = null;
    protected ?string $laravel_config_name = null;
    protected ?string $view_namespace = null;

    /* -------------------------------------------------
     | Magic Methods
     |--------------------------------------------------*/
    public function __construct(string $moduleName)
    {
        $this->module_name = $moduleName;
        $this->initialize();
    }

    /* -------------------------------------------------
     | Abstract Methods
     |--------------------------------------------------*/
    /**
     * Define module-specific paths and classes
     */
    abstract protected function definePackageInfo(): void;

    /* -------------------------------------------------
     | Path Methods (ALL existing paths)
     |--------------------------------------------------*/
    /**
     * Get ALL migrations paths: custom + default (if exist)
     */
    public function getMigrationsPaths(): array
    {
        $paths = [];
        if ($this->custom_migrations_path && is_dir($this->custom_migrations_path)) {
            $paths[] = $this->custom_migrations_path;
        }
        if (is_dir($this->default_migrations_path)) {
            $paths[] = $this->default_migrations_path;
        }
        return $paths;
    }

    /**
     * Get ALL views paths: custom + default (if exist)
     */
    public function getViewsPaths(): array
    {
        $paths = [];
        if ($this->custom_views_path && is_dir($this->custom_views_path)) {
            $paths[] = $this->custom_views_path;
        }
        if (is_dir($this->default_views_path)) {
            $paths[] = $this->default_views_path;
        }
        return $paths;
    }

    /**
     * Get ALL config paths: custom + default (if exist)
     */
    public function getConfigPaths(): array
    {
        $paths = [];
        if ($this->custom_config_path && file_exists($this->custom_config_path)) {
            $paths[] = $this->custom_config_path;
        }
        if (file_exists($this->default_config_path)) {
            $paths[] = $this->default_config_path;
        }
        return $paths;
    }

    /**
     * Get ALL routes paths: custom + default (if exist)
     */
    public function getRoutesPaths(): array
    {
        $paths = [];
        if ($this->custom_routes_path && file_exists($this->custom_routes_path)) {
            $paths[] = $this->custom_routes_path;
        }
        if (file_exists($this->default_routes_path)) {
            $paths[] = $this->default_routes_path;
        }
        return $paths;
    }

    /* -------------------------------------------------
     | Class Methods (specific only)
     |--------------------------------------------------*/
    /**
     * Get specific providers only
     */
    public function getProviders(): ?array
    {
        return $this->specific_providers;
    }

    /**
     * Get specific console commands only
     */
    public function getConsoleCommands(): ?array
    {
        return $this->specific_commands;
    }

    /**
     * Get specific middlewares only
     */
    public function getMiddlewares(): ?array
    {
        return $this->specific_middlewares;
    }

    /**
     * Get specific models only
     */
    public function getModels(): ?array
    {
        return $this->specific_models;
    }

    /* -------------------------------------------------
     | Protected Methods
     |--------------------------------------------------*/
    /**
     * Initialize package properties
     */
    protected function initialize(): void
    {
        $resolver = $this->getNamespaceResolver();

        $this->package_name = Str::studly($this->module_name);
        $this->conf_name = strtolower($this->module_name);
        $this->view_namespace = $this->package_name;

        $packageRoot = $this->getPackagePath();
        $this->default_migrations_path = $packageRoot . '/' . static::MIGRATIONS_PATH;
        $this->default_views_path = $packageRoot . '/' . static::VIEWS_PATH;
        $this->default_config_path = $packageRoot . '/' . static::CONFIG_DIR . '/' . $this->conf_name . '.php';
        $this->default_routes_path = $packageRoot . '/' . static::ROUTES_WEB_PATH;

        $this->definePackageInfo();
    }

    /**
     * Get namespace resolver instance
     */
    protected function getNamespaceResolver(): NamespaceResolver
    {
        return new NamespaceResolver($this->module_name);
    }

    /**
     * Get package root path
     */
    protected function getPackagePath(): string
    {
        return base_path("modules/{$this->module_name}");
    }
}
