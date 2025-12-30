<?php

namespace Iquesters\Foundation\System\Package;

use Illuminate\Support\Str;
use ReflectionClass;

abstract class PackageInfo
{
    /* -------------------------------------------------
     | Constants
     |--------------------------------------------------*/
    private const VENDOR_NAME = 'iquesters';

    protected const ROUTES_WEB_PATH      = 'routes/web.php';
    protected const MIGRATIONS_PATH      = 'database/migrations';
    protected const VIEWS_PATH           = 'resources/views';
    protected const CONFIG_DIR           = 'config';
    protected const DIRECTORY_SEPARATOR  = '/';

    /* -------------------------------------------------
     | Properties
     |--------------------------------------------------*/
    protected string $module_name = '';

    protected ?string $package_name = null;
    protected ?string $conf_name = null;

    protected bool $load_laravel_native_config = false;
    
    protected string $seeder_class;

    protected ?array $console_commands = null;

    protected ?string $laravel_config_name = null;
    protected ?string $laravel_config_path = null;

    protected ?array  $routes = null;
    protected ?string $migrations_path = null;
    protected ?string $views_path = null;
    protected ?string $view_namespace = null;
    protected ?array $middlewares = null;

    protected ?string $base_path = null;

    /* -------------------------------------------------
     | Package
     |--------------------------------------------------*/
    public function getPackageName(): string
    {
        return $this->package_name
            ??= self::VENDOR_NAME . self::DIRECTORY_SEPARATOR . $this->module_name;
    }

    public function moduleName(): string
    {
        return $this->module_name;
    }

    /* -------------------------------------------------
     | Base path
     |--------------------------------------------------*/
    // public function basePath(string $path = ''): string
    // {
    //     if ($this->base_path === null) {
    //         $reflection = new ReflectionClass(static::class);
    //         // src/PackageInfo.php -> src -> package root
    //         $this->base_path = dirname(dirname($reflection->getFileName()));
    //     }

    //     return $path
    //         ? $this->base_path . self::DIRECTORY_SEPARATOR . ltrim($path, self::DIRECTORY_SEPARATOR)
    //         : $this->base_path;
    // }
    public function basePath(string $path = ''): string
    {
        if ($this->base_path === null) {
            $reflection = new \ReflectionClass(static::class);
            $dir = dirname($reflection->getFileName());

            // Walk up until we find composer.json (package root)
            while (!is_file($dir . '/composer.json')) {
                $parent = dirname($dir);

                if ($parent === $dir) {
                    throw new \RuntimeException(
                        'Unable to locate package root (composer.json not found)'
                    );
                }

                $dir = $parent;
            }

            $this->base_path = $dir;
        }

        return $path
            ? $this->base_path . '/' . ltrim($path, '/')
            : $this->base_path;
    }

    /* -------------------------------------------------
     | Routes
     |--------------------------------------------------*/
    public function routes(): array
    {
        $routes = $this->routes ?? [$this->basePath(self::ROUTES_WEB_PATH)];
        return array_filter($routes, 'is_file'); // Only return existing files
    }

    /* -------------------------------------------------
     | Migrations
     |--------------------------------------------------*/
    public function migrationsPath(): string
    {
        return $this->migrations_path
            ?? $this->basePath(self::MIGRATIONS_PATH);
    }

    /* -------------------------------------------------
     | Views
     |--------------------------------------------------*/
    public function viewsPath(): string
    {
        return $this->views_path
            ?? $this->basePath(self::VIEWS_PATH);
    }

    public function viewNamespace(): string
    {
        return $this->view_namespace
            ?? $this->module_name;
    }

    /* -------------------------------------------------
     | Middlewares
     |--------------------------------------------------*/
    public function middlewares(): array
    {
        return $this->middlewares ?? [];
    }

    /* -------------------------------------------------
     | Seeder
     |--------------------------------------------------*/
    public function seederClass(): string
    {
        if (empty($this->seeder_class)) {
            throw new \RuntimeException('Seeder class must be defined in PackageInfo');
        }

        // Automatically resolve the namespace based on VENDOR_NAME and module_name
        $vendorNamespace = Str::studly(self::VENDOR_NAME);
        $moduleNamespace = Str::studly($this->module_name);

        return $vendorNamespace . '\\' . $moduleNamespace . '\\Database\\Seeders\\' . $this->seeder_class;
    }

    /* -------------------------------------------------
     | Console Command
     |--------------------------------------------------*/
    public function consoleCommands(): array
    {
        return $this->console_commands ?? [];
    }
    
    /* -------------------------------------------------
     | Custom Conf
     |--------------------------------------------------*/
    public function confName(): string
    {
        return $this->conf_name
            ??= Str::studly($this->module_name) . 'Conf';
    }

    public function configClass(): string
    {
        return static::classNamespace() . '\\' . $this->confName();
    }

    /* -------------------------------------------------
     | Laravel Native Config
     |--------------------------------------------------*/
    public function shouldLoadLaravelNativeConfig(): bool
    {
        return $this->load_laravel_native_config;
    }

    public function laravelConfigName(): string
    {
        return $this->laravel_config_name
            ?? $this->module_name;
    }

    public function laravelConfigPath(): string
    {
        return $this->laravel_config_path
            ?? $this->basePath(
                self::CONFIG_DIR
                . self::DIRECTORY_SEPARATOR
                . $this->module_name
                . '.php'
            );
    }

    /* -------------------------------------------------
     | Helpers
     |--------------------------------------------------*/
    protected static function classNamespace(): string
    {
        return substr(static::class, 0, strrpos(static::class, '\\'));
    }

}