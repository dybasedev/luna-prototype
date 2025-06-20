<?php

namespace Dybasedev\LunaPrototype\Schedule;

use Illuminate\Support\ServiceProvider;

class LunaScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);
    }
}