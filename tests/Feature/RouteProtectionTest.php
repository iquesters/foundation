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

    public function test_foundation_routes_require_auth_and_admin(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getActionName(), 'Iquesters\\Foundation\\'))
            ->reject(fn (Route $route) => in_array($route->uri(), self::PUBLIC_URIS, true));

        $this->assertNotEmpty($routes, 'No foundation routes were registered.');

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
