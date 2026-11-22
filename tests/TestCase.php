<?php

declare(strict_types=1);

namespace G8Scope\Core\Tests;

use CleaniqueCoders\Traitify\TraitifyServiceProvider;
use G8Scope\Core\G8ScopeCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use OwenIt\Auditing\AuditingServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TraitifyServiceProvider::class,
            AuditingServiceProvider::class,
            G8ScopeCoreServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // --- Postgres (mutable state) ---
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('BLIPTRACE_PG_HOST', env('G8SCOPE_PG_HOST', '127.0.0.1')),
            'port' => env('BLIPTRACE_PG_PORT', env('G8SCOPE_PG_PORT', '55432')),
            'database' => env('BLIPTRACE_PG_DB', env('G8SCOPE_PG_DB', 'g8scope_core_test')),
            'username' => env('BLIPTRACE_PG_USER', env('G8SCOPE_PG_USER', 'g8scope')),
            'password' => env('BLIPTRACE_PG_PASS', env('G8SCOPE_PG_PASS', 'g8scope')),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        // owen-it/laravel-auditing skips auditing in console by default; tests run
        // in console, so enable it here.
        $app['config']->set('audit.console', true);
    }
}
