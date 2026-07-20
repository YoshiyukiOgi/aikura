<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // PHPUnit's forced environment values are exposed through getenv().
        // Prefer them over Docker Compose values retained in $_SERVER so the
        // documented test command uses the isolated testing database.
        $environment = (string) (getenv('APP_ENV') ?: $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '');
        $database = (string) (getenv('DB_DATABASE') ?: $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? '');

        if ($environment !== 'testing' || ! str_contains($database, 'testing')) {
            throw new RuntimeException("Refusing to run tests against non-testing database [{$database}].");
        }

        parent::setUp();
    }
}
