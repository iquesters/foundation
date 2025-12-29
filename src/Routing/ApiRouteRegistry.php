<?php

namespace Iquesters\Foundation\Routing;

class ApiRouteRegistry
{
    protected static array $routes = [];

    public static function register(
        string $method,
        string $uri,
        string $package
    ): void {
        $key = "{$method}:{$uri}";

        if (isset(self::$routes[$key])) {
            throw new \RuntimeException(
                "API route collision detected\n".
                "Route: {$uri}\n".
                "Packages: {$package} & ".self::$routes[$key]
            );
        }

        self::$routes[$key] = $package;
    }

    public static function all(): array
    {
        return self::$routes;
    }
}