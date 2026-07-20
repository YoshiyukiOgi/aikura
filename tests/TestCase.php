<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Docker-injected server variables are what Laravel resolves first.
        // Never allow PHPUnit's process-level overrides to mask a production
        // database configured on the container.
        $environment = (string) ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        $database = (string) ($_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');

        if ($environment !== 'testing' || ! str_contains($database, 'testing')) {
            throw new RuntimeException("Refusing to run tests against non-testing database [{$database}].");
        }

        parent::setUp();
    }
}
