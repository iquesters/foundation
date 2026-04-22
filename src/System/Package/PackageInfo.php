<?php

namespace Iquesters\Foundation\System\Package;

use Iquesters\Foundation\System\Package\NamespaceResolver;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

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
    protected NamespaceResolver $resolver;

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

    // Auto-discovered classes (can be overridden)
    protected ?array $auto_providers = null;
    protected ?array $auto_commands = null;
    protected ?array $auto_middleware_aliases = null;
    protected ?array $auto_seeder_bindings = null;

    // Manual overrides (only if you want to skip auto-discovery)
    protected ?array $specific_providers = null;
    protected ?array $specific_commands = null;
    protected ?array $specific_middleware_aliases = null;
    protected ?array $specific_models = null;

    // Common properties
    protected ?string $package_name = null;
    protected ?string $conf_name = null;
    protected ?string $laravel_config_name = null;
    
    // ConfProvider integration (auto-discovered)
    protected ?string $auto_conf_class = null;
    protected $auto_conf_module = null;
    
    // Publishing (auto-discovered)
    protected ?array $auto_publishable_assets = null;

    /* -------------------------------------------------
     | Magic Methods
     |--------------------------------------------------*/
    public function __construct(string $moduleName)
    {
        $this->module_name = $moduleName;
        $this->resolver = new NamespaceResolver($moduleName);
        $this->initialize();
    }

    /* -------------------------------------------------
     | Abstract Methods
     |--------------------------------------------------*/
    /**
     * Define module-specific overrides (optional)
     */
    abstract protected function definePackageInfo(): void;

    /* -------------------------------------------------
     | Auto-Discovery Methods
     |--------------------------------------------------*/
    
    /**
     * Auto-discover all service providers in Providers namespace
     */
    protected function autoDiscoverProviders(): array
    {
        $providers = [];
        $providersNamespace = $this->resolver->getProvidersNamespace();
        $providersPath = $this->namespaceToPath($providersNamespace);

        if (!is_dir($providersPath)) {
            // \Log::debug("Providers directory not found", ['path' => $providersPath]);
            return $providers;
        }

        $files = File::files($providersPath);
        
        foreach ($files as $file) {
            $className = $providersNamespace . '\\' . $file->getFilenameWithoutExtension();
            
            if (class_exists($className) && $this->isServiceProvider($className)) {
                $providers[] = $className;
                // \Log::debug("Auto-discovered provider", ['class' => $className]);
            }
        }

        return $providers;
    }

    /**
     * Auto-discover all console commands in Console namespace
     */
    protected function autoDiscoverCommands(): array
    {
        $commands = [];
        $consoleNamespace = $this->resolver->getConsoleNamespace();
        $consolePath = $this->namespaceToPath($consoleNamespace);

        if (!is_dir($consolePath)) {
            // \Log::debug("Console directory not found", ['path' => $consolePath]);
            return $commands;
        }

        $files = File::files($consolePath);
        
        foreach ($files as $file) {
            $className = $consoleNamespace . '\\' . $file->getFilenameWithoutExtension();
            
            if (class_exists($className) && $this->isCommand($className)) {
                // Skip SeederCommand - it needs special handling
                if (class_basename($className) === 'SeederCommand') {
                    continue;
                }
                
                $commands[] = $className;
                // \Log::debug("Auto-discovered command", ['class' => $className]);
            }
        }

        return $commands;
    }

    /**
     * Auto-discover seeder and return binding info for SeederCommand
     * Returns array of ['command_class' => string, 'seeder_class' => string]
     */
    protected function autoDiscoverSeederBindings(): array
    {
        $bindings = [];
        
        // Seeders are in database/seeders, not src/Database/Seeders
        $packageRoot = $this->getPackagePath();
        $seederPath = $packageRoot . '/database/seeders';

        // Log::debug("Looking for seeders directory", [
        //     'module' => $this->module_name,
        //     'package_root' => $packageRoot,
        //     'seeder_path' => $seederPath,
        //     'exists' => is_dir($seederPath)
        // ]);

        if (!is_dir($seederPath)) {
            \Log::debug("Seeders directory not found", [
                'module' => $this->module_name,
                'path' => $seederPath
            ]);
            return $bindings;
        }

        // Use namespace for the seeder class
        $seederNamespace = $this->resolver->getSeedersNamespace();
        $expectedSeederName = $this->resolver->getModuleNamespace() . 'Seeder';
        $seederClass = $seederNamespace . '\\' . $expectedSeederName;

        // Log::debug("Checking for seeder class", [
        //     'module' => $this->module_name,
        //     'seeder_namespace' => $seederNamespace,
        //     'expected_name' => $expectedSeederName,
        //     'full_class' => $seederClass,
        //     'exists' => class_exists($seederClass)
        // ]);

        if (!class_exists($seederClass)) {
            \Log::warning("Seeder class not found", [
                'module' => $this->module_name,
                'expected_class' => $seederClass,
                'seeder_path' => $seederPath
            ]);
            return $bindings;
        }

        // Look for SeederCommand in module's Console namespace
        $seederCommandClass = $this->resolver->getConsoleNamespace() . '\\SeederCommand';
        
        // If not found in module, use Foundation's SeederCommand
        if (!class_exists($seederCommandClass)) {
            $seederCommandClass = 'Iquesters\\Foundation\\Console\\SeederCommand';
            // \Log::debug("Using Foundation's SeederCommand", [
            //     'module' => $this->module_name,
            //     'command_class' => $seederCommandClass
            // ]);
        }
        
        if (class_exists($seederCommandClass)) {
            $bindings[] = [
                'binding_name' => 'command.' . $this->module_name . '.seed',
                'command_class' => $seederCommandClass,
                'seeder_class' => $seederClass
            ];
            
            // Log::info("Auto-discovered seeder binding", [
            //     'module' => $this->module_name,
            //     'seeder' => $seederClass,
            //     'command' => $seederCommandClass
            // ]);
        } else {
            \Log::error("SeederCommand class not found", [
                'module' => $this->module_name,
                'tried_classes' => [
                    $this->resolver->getConsoleNamespace() . '\\SeederCommand',
                    'Iquesters\\Foundation\\Console\\SeederCommand'
                ]
            ]);
        }

        return $bindings;
    }

    /**
     * Auto-discover middleware and create aliases
     */
    protected function autoDiscoverMiddlewareAliases(): array
    {
        $aliases = [];
        $middlewareNamespace = $this->resolver->getMiddlewareNamespace();
        $middlewarePath = $this->namespaceToPath($middlewareNamespace);

        if (!is_dir($middlewarePath)) {
            // \Log::debug("Middleware directory not found", ['path' => $middlewarePath]);
            return $aliases;
        }

        $files = File::files($middlewarePath);
        
        foreach ($files as $file) {
            $className = $middlewareNamespace . '\\' . $file->getFilenameWithoutExtension();
            
            if (class_exists($className)) {
                // Convert ValidatePlatformVersion -> validate.platform.version
                $alias = $this->generateMiddlewareAlias($file->getFilenameWithoutExtension());
                $aliases[$alias] = $className;
                
                // Log::debug("Auto-discovered middleware", [
                //     'class' => $className,
                //     'alias' => $alias
                // ]);
            }
        }

        return $aliases;
    }

    /**
     * Auto-discover Config class for ConfProvider
     */
    protected function autoDiscoverConfClass(): ?string
    {
        $configNamespace = $this->resolver->getConfigNamespace();
        $expectedConfName = $this->resolver->getModuleNamespace() . 'Conf';
        $confClass = $configNamespace . '\\' . $expectedConfName;

        if (class_exists($confClass)) {
            // \Log::debug("Auto-discovered config class", ['class' => $confClass]);
            return $confClass;
        }

        return null;
    }

    /**
     * Auto-discover Module enum value
     */
    protected function autoDiscoverConfModule()
    {
        // Try local Module enum first
        $enumsNamespace = $this->resolver->getEnumsNamespace();
        $moduleEnumClass = $enumsNamespace . '\\Module';

        if (!class_exists($moduleEnumClass)) {
            // Fallback to Foundation's Module enum
            $moduleEnumClass = 'Iquesters\\Foundation\\Enums\\Module';
        }

        if (class_exists($moduleEnumClass)) {
            // Try uppercase snake case: foundation -> FOUNDATION
            $moduleName = strtoupper(Str::snake($this->module_name));
            
            if (defined("{$moduleEnumClass}::{$moduleName}")) {
                $enumValue = constant("{$moduleEnumClass}::{$moduleName}");
                // \Log::debug("Auto-discovered module enum", [
                //     'enum_class' => $moduleEnumClass,
                //     'constant' => $moduleName,
                //     'value' => $enumValue
                // ]);
                return $enumValue;
            }
            
            // If not found, log available constants for debugging
            try {
                $reflection = new \ReflectionClass($moduleEnumClass);
                $constants = $reflection->getConstants();
                \Log::warning("Module constant not found", [
                    'expected_constant' => $moduleName,
                    'available_constants' => array_keys($constants),
                    'enum_class' => $moduleEnumClass
                ]);
            } catch (\ReflectionException $e) {
                \Log::warning("Could not reflect Module enum", ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Auto-discover publishable assets
     */
    protected function autoDiscoverPublishableAssets(): array
    {
        $assets = [];
        $packageRoot = $this->getPackagePath();
        $tag = $this->conf_name . '-config';

        // Config file
        if (file_exists($this->default_config_path)) {
            $assets[$tag][$this->default_config_path] = config_path($this->conf_name . '.php');
        }

        // Views (look for layouts/package.blade.php as example)
        $packageLayoutPath = $packageRoot . '/resources/views/layouts/package.blade.php';
        if (file_exists($packageLayoutPath)) {
            $viewTag = $this->conf_name . '-views';
            $assets[$viewTag][$packageLayoutPath] = resource_path(
                'views/vendor/' . $this->conf_name . '/layouts/package.blade.php'
            );
        }

        return $assets;
    }

    /* -------------------------------------------------
     | Helper Methods
     |--------------------------------------------------*/
    
    /**
     * Convert namespace to filesystem path
     */
    protected function namespaceToPath(string $namespace): string
    {
        $packageRoot = $this->getPackagePath();
        
        // Get the base namespace (Iquesters\Foundation)
        $baseNamespace = $this->resolver->getNamespace();
        
        // Remove base namespace prefix
        // Iquesters\Foundation\Console -> Console
        // Iquesters\Foundation\Database\Seeders -> Database\Seeders
        if (strpos($namespace, $baseNamespace . '\\') === 0) {
            $relativePath = substr($namespace, strlen($baseNamespace) + 1);
        } else {
            $relativePath = $namespace;
        }
        
        // Convert namespace separators to directory separators
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativePath);
        
        // Assuming PSR-4: src/{RelativePath}
        return $packageRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath;
    }

    /**
     * Check if class is a ServiceProvider
     */
    protected function isServiceProvider(string $className): bool
    {
        try {
            $reflection = new \ReflectionClass($className);
            return $reflection->isSubclassOf('Illuminate\\Support\\ServiceProvider') 
                && !$reflection->isAbstract();
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Check if class is a Command
     */
    protected function isCommand(string $className): bool
    {
        try {
            $reflection = new \ReflectionClass($className);
            return $reflection->isSubclassOf('Illuminate\\Console\\Command') 
                && !$reflection->isAbstract();
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Generate middleware alias from class name
     * ValidatePlatformVersion -> validate.platform.version
     */
    protected function generateMiddlewareAlias(string $className): string
    {
        return Str::snake($className, '.');
    }

    /* -------------------------------------------------
     | Path Collection Methods
     |--------------------------------------------------*/
    
    protected function collectPaths(
        string $type,
        ?string $customPath,
        string $defaultPath,
        bool $mustBeDir = true
    ): array {
        $paths = [];
        $checker = $mustBeDir ? 'is_dir' : 'file_exists';

        if ($customPath && $checker($customPath)) {
            $paths[] = $customPath;
        }

        if ($checker($defaultPath)) {
            $paths[] = $defaultPath;
        }

        return $paths;
    }
    
    public function getMigrationsPaths(): array
    {
        return $this->collectPaths(
            type: 'migrations',
            customPath: $this->custom_migrations_path,
            defaultPath: $this->default_migrations_path,
            mustBeDir: true
        );
    }

    public function getViewsPaths(): array
    {
        return $this->collectPaths(
            type: 'views',
            customPath: $this->custom_views_path,
            defaultPath: $this->default_views_path,
            mustBeDir: true
        );
    }

    public function getConfigPaths(): array
    {
        return $this->collectPaths(
            type: 'config',
            customPath: $this->custom_config_path,
            defaultPath: $this->default_config_path,
            mustBeDir: false
        );
    }

    public function getRoutesPaths(): array
    {
        return $this->collectPaths(
            type: 'routes',
            customPath: $this->custom_routes_path,
            defaultPath: $this->default_routes_path,
            mustBeDir: false
        );
    }

    /* -------------------------------------------------
     | Getters (with fallback to auto-discovery)
     |--------------------------------------------------*/
    
    public function getProviders(): ?array
    {
        return $this->specific_providers ?? $this->auto_providers;
    }

    public function getConsoleCommands(): ?array
    {
        return $this->specific_commands ?? $this->auto_commands;
    }

    public function getSeederBindings(): ?array
    {
        return $this->auto_seeder_bindings;
    }

    public function getMiddlewareAliases(): ?array
    {
        return $this->specific_middleware_aliases ?? $this->auto_middleware_aliases;
    }

    public function getModels(): ?array
    {
        return $this->specific_models;
    }

    public function getConfClass(): ?string
    {
        return $this->auto_conf_class;
    }

    public function getConfModule()
    {
        return $this->auto_conf_module;
    }

    public function getPublishableAssets(): ?array
    {
        return $this->auto_publishable_assets;
    }

    public function getViewNamespace(): string
    {
        return strtolower($this->module_name);
    }

    public function getConfName(): string
    {
        return $this->conf_name;
    }

    /* -------------------------------------------------
     | Initialization
     |--------------------------------------------------*/
    
    protected function initialize(): void
    {
        $this->package_name = Str::studly($this->module_name);
        $this->conf_name = strtolower($this->module_name);

        $packageRoot = $this->getPackagePath();
        $this->default_migrations_path = $packageRoot . '/' . static::MIGRATIONS_PATH;
        $this->default_views_path = $packageRoot . '/' . static::VIEWS_PATH;
        $this->default_config_path = $packageRoot . '/' . static::CONFIG_DIR . '/' . $this->conf_name . '.php';
        $this->default_routes_path = $packageRoot . '/' . static::ROUTES_WEB_PATH;

        // Auto-discover everything
        $this->auto_providers = $this->autoDiscoverProviders();
        $this->auto_commands = $this->autoDiscoverCommands();
        $this->auto_seeder_bindings = $this->autoDiscoverSeederBindings();
        $this->auto_middleware_aliases = $this->autoDiscoverMiddlewareAliases();
        $this->auto_conf_class = $this->autoDiscoverConfClass();
        $this->auto_conf_module = $this->autoDiscoverConfModule();
        $this->auto_publishable_assets = $this->autoDiscoverPublishableAssets();

        // Allow manual overrides
        $this->definePackageInfo();
    }

    protected function getNamespaceResolver(): NamespaceResolver
    {
        return $this->resolver;
    }

    protected function getPackagePath(): string
    {
        $path = dirname((new \ReflectionClass($this))->getFileName());

        while (!file_exists($path . '/composer.json')) {
            $path = dirname($path);
        }

        return $path;
    }
}
