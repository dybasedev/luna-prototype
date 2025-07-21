<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Illuminate\Support\ServiceProvider;

class LunaHoldingObjectServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 注册配置类
        $this->app->singleton(LunaHoldingObjectConfigure::class, function () {
            return new LunaHoldingObjectConfigure();
        });
        
        // 使用配置类的 register 方法注册服务
        $configure = $this->app->make(LunaHoldingObjectConfigure::class);
        $configure->register($this->app);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ], 'luna-holding-object-migrations');
        }
    }
}