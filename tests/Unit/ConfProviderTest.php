<?php

namespace Iquesters\Foundation\Tests\Unit;

use Iquesters\Foundation\Enums\Module;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Tests\TestCase;

class ConfProviderTest extends TestCase
{
    public function test_reuses_the_instance_within_a_request(): void
    {
        $this->assertSame(ConfProvider::from(Module::FOUNDATION), ConfProvider::from(Module::FOUNDATION));
    }

    public function test_forget_and_clear_cache_rebuild_the_instance(): void
    {
        $first = ConfProvider::from(Module::FOUNDATION);

        ConfProvider::forget(Module::FOUNDATION);
        $second = ConfProvider::from(Module::FOUNDATION);
        $this->assertNotSame($first, $second);

        $second->clearCache();
        $this->assertNotSame($second, ConfProvider::from(Module::FOUNDATION));
    }

    public function test_instances_are_dropped_between_requests_and_jobs(): void
    {
        $first = ConfProvider::from(Module::FOUNDATION);

        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, ConfProvider::from(Module::FOUNDATION));
    }
}
