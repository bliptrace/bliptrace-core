<?php

declare(strict_types=1);

namespace G8scope\Core;

use Illuminate\Support\ServiceProvider;

class G8scopeCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/g8scope.php', 'g8scope');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/g8scope.php' => config_path('g8scope.php'),
            ], 'g8scope-config');
        }
    }
}
