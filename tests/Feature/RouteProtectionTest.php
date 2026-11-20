<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Routing\Route;
use Iquesters\Foundation\Tests\TestCase;

/**
 * Every foundation web route except the few public ones must require
 * auth + ensure.admin, so a new route can't ship unprotected by accident.
 */
class RouteProtectionTest extends TestCase
{
    /** Foundation routes that are intentionally not admin-only. */
    private const PUBLIC_URIS = [
        'timezone',
    ];

    /**
     * Data APIs open to non-admins: each action checks the per-entity
     * entity.{table}.{action} permission instead (see EntityPermissionTest),
     * so they only need a Sanctum-authenticated user.
     */
    private const PERMISSION_CHECKED_PREFIXES = [
        'api/foundation/v1/entity/',
        'api/foundation/v1/business-entity/',
    ];

    public function test_foundation_routes_require_auth_and_admin(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getActionName(), 'Iquesters\\Foundation\\'))
            ->reject(fn (Route $route) => in_array($route->uri(), self::PUBLIC_URIS, true));

        $this->assertNotEmpty($routes, 'No foundation routes were registered.');

        [$dataApi, $routes] = $routes->partition(fn (Route $route) => str($route->uri())->startsWith(self::PERMISSION_CHECKED_PREFIXES));

        $this->assertNotEmpty($dataApi, 'The entity data API routes were not registered.');

        foreach ($dataApi as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "Route [{$route->uri()}] is missing auth:sanctum.");
        }

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('ensure.admin', $middleware, "Route [{$route->uri()}] is missing ensure.admin.");
            $this->assertContains('auth', $middleware, "Route [{$route->uri()}] is missing auth.");
        }
    }

    public function test_queue_management_api_rejects_guests_and_non_admins(): void
    {
        $this->getJson('/api/smart-messenger/queue-management/queues')->assertUnauthorized();

        $this->actingAs($this->makeUser('user'))
            ->getJson('/api/smart-messenger/queue-management/queues')
            ->assertForbidden();
    }

    public function test_dispatch_queue_route_is_absent_without_smart_messenger(): void
    {
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('dispatch-queue'));
        $this->postJson('/api/dispatch-queue')->assertNotFound();
    }
}
