<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Most feature tests exercise installed-app behavior. Installation
        // tests explicitly opt out so they can exercise the real guard.
        config(['semizzy.testing_installed' => true]);
    }
}
