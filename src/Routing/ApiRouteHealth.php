<?php

namespace Iquesters\Foundation\Routing;

class ApiRouteHealth
{
    public static function status(): array
    {
        return [
            'routes' => count(ApiRouteRegistry::all()),
            'status' => 'ok',
        ];
    }
}