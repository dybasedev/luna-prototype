<?php

namespace Dybasedev\LunaPrototype\Membership;

use Illuminate\Support\ServiceProvider;

/**
 * Luna 会员系统服务提供者
 *
 * 负责注册会员系统相关的服务、发布配置文件、数据库迁移等
 */
class LunaMembershipServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 服务已在 LunaMembershipConfigure 中注册
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__ . '/migrations/' => database_path('migrations'),
            ]);
        }
    }
}