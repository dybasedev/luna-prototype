<?php

namespace Dybasedev\LunaPrototype\Content;

use Illuminate\Support\ServiceProvider;

class LunaContentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // 发布迁移文件
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ], 'luna-content-migrations');
        }
    }
}