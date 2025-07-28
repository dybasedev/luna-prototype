<?php

namespace Dybasedev\LunaPrototype\DnW;

use Illuminate\Support\ServiceProvider;

/**
 * 出入金模块服务提供者
 */
class LunaDnWServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);
    }
}