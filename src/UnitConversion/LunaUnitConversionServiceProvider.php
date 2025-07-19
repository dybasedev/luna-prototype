<?php

namespace Dybasedev\LunaPrototype\UnitConversion;

use Illuminate\Support\ServiceProvider;

class LunaUnitConversionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);
    }
}