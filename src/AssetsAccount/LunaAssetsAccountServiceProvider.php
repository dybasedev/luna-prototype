<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Illuminate\Support\ServiceProvider;

class LunaAssetsAccountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);

        $this->app[LunaHandlerConfigure::class]->group('account', '账户', function ($register) {
            $register->handler(StandardAccountHandler::class);
        });
    }
}