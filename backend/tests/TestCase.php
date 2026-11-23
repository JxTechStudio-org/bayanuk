<?php

namespace Tests;

use App\Services\Security\VirusScanner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ClamAvScanner fails closed when ClamAV is unreachable (as in CI), which would reject
        // every upload as unsafe_file. Default to a clean scan; tests that need another result
        // bind their own mock with app()->instance(), which replaces this one.
        $scanner = Mockery::mock(VirusScanner::class);
        $scanner->shouldReceive('isSafe')->andReturn(true);
        $this->app->instance(VirusScanner::class, $scanner);
    }
}
