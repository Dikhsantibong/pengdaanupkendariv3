<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The archive download caps its own run at 300 seconds. Within the test
     * process that cap would carry on to every later test (on Windows it
     * counts wall-clock time), so it is lifted after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        set_time_limit(0);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
