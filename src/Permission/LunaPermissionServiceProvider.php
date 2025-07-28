<?php

namespace Dybasedev\LunaPrototype\Permission;

use Illuminate\Support\ServiceProvider;

/**
 * Luna 权限模块服务提供者
 */
class LunaPermissionServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 权限组件的服务注册由 LunaPermissionConfigure 处理
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
            ], 'luna-permission-migrations');
        }
    }
}