<?php

namespace Dybasedev\LunaPrototype\Trade;

use Illuminate\Support\ServiceProvider;

/**
 * 交易组件服务提供者
 * 
 * 负责注册交易组件的迁移文件、配置文件等
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaTradeServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 服务已在 LunaTradeConfigure 中注册
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // 发布迁移文件
            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ], 'luna-trade-migrations');
            
            // 加载迁移文件
            $this->loadMigrationsFrom(__DIR__ . '/migrations');
        }
    }
}