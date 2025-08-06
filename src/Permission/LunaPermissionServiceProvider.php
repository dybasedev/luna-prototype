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
        
        // 加载辅助函数
        if (file_exists(__DIR__ . '/helpers.php')) {
            require_once __DIR__ . '/helpers.php';
        }
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
            
            // 注册命令
            $this->commands([
                Console\ResourceCacheCommand::class,
            ]);
        }
        
        // 注册中间件
        $this->app['router']->aliasMiddleware('permission', Http\Middleware\CheckPermission::class);
    }
}