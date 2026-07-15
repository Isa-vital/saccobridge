<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Static per-request GL account cache must not leak between tests
        \App\Services\GlCodes::flush();

        // Feature tests exercise tenant-context routes against the single
        // test database — tenancy identification itself is covered separately.
        // Tenant migrations are loaded via AppServiceProvider when testing.
        $this->withoutMiddleware([
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    }
}
