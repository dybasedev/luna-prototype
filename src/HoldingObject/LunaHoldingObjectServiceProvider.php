<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Illuminate\Support\ServiceProvider;

class LunaHoldingObjectServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ]);
        }
    }
}