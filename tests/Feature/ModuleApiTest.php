<?php

namespace Iquesters\Foundation\Tests\Feature;

use Iquesters\Foundation\Tests\TestCase;

class ModuleApiTest extends TestCase
{
    public function test_api_index_lists_module_versions(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('modules.foundation', ['v1']);
    }

    public function test_each_module_version_has_a_status_route(): void
    {
        // ResponseMiddleware wraps API responses: payload is in response_schema.data.
        $this->getJson('/api/foundation/v1')
            ->assertOk()
            ->assertJsonPath('response_schema.data.module', 'foundation')
            ->assertJsonPath('response_schema.data.version', 'v1');
    }

    public function test_old_double_versioned_routes_are_gone(): void
    {
        $this->getJson('/api/v1/foundation/v1')->assertNotFound();
    }

    public function test_versioned_routes_are_named_per_module_and_version(): void
    {
        $this->assertTrue(app('router')->has('api.foundation.v1.status'));
    }
}
