<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Illuminate\Support\ServiceProvider;

class LunaAssetsAccountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);
    }
}