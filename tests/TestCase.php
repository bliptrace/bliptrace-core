<?php

declare(strict_types=1);

namespace G8scope\Core\Tests;

use G8scope\Core\G8scopeCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use OwenIt\Auditing\AuditingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            AuditingServiceProvider::class,
            G8scopeCoreServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // --- Postgres (mutable state) ---
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('G8SCOPE_PG_HOST', '127.0.0.1'),
            'port' => env('G8SCOPE_PG_PORT', '55432'),
            'database' => env('G8SCOPE_PG_DB', 'g8scope_core_test'),
            'username' => env('G8SCOPE_PG_USER', 'g8scope'),
            'password' => env('G8SCOPE_PG_PASS', 'g8scope'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        // --- ClickHouse (occurrences) — consumed by the OccurrenceStore driver ---
        $app['config']->set('g8scope.clickhouse', [
            'host' => env('G8SCOPE_CH_HOST', '127.0.0.1'),
            'port' => (int) env('G8SCOPE_CH_PORT', '8123'),
            'username' => env('G8SCOPE_CH_USER', 'g8scope'),
            'password' => env('G8SCOPE_CH_PASS', 'g8scope'),
            'database' => env('G8SCOPE_CH_DB', 'g8scope_core_test'),
        ]);
    }
}
