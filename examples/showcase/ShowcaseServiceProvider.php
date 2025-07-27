<?php

namespace Examples\Showcase;

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;

/**
 * Showcase 服务提供者示例
 * 
 * 展示如何在 Laravel 应用中注册和配置 Showcase 组件
 * 
 * 使用示例：
 * 1. 在你的项目中创建一个继承此类的服务提供者
 * 2. 在 customRegister 方法中注册 LunaShowcaseConfigure
 * 3. 在 config/app.php 中注册你的服务提供者
 */
class ShowcaseServiceProvider extends LunaServiceProvider
{
    /**
     * 自定义注册
     */
    public function customRegister(): void
    {
        // 注册 Showcase 模块配置
        $this->registerModule(
            LunaShowcaseConfigure::create()
                // 注册自定义适配器（可选）
                ->registerAdapter('custom-ui', \App\Showcase\Adapters\CustomUIAdapter::class)
                ->setDefaultAdapter('ant-design-pro')
                
                // 手动注册 DataTable
                ->registerDataTable('users', \App\DataTables\UserDataTable::class, [
                    'title' => '用户管理',
                    'description' => '管理系统用户账号',
                    'group' => 'system',
                    'sortOrder' => 10,
                ])
                ->registerDataTable('roles', \App\DataTables\RoleDataTable::class, [
                    'title' => '角色管理',
                    'description' => '管理系统角色权限',
                    'group' => 'system',
                    'sortOrder' => 20,
                ])
                
                // 批量注册 DataTable
                ->registerDataTables([
                    'logs' => [
                        'class' => \App\DataTables\LogDataTable::class,
                        'title' => '操作日志',
                        'description' => '查看系统操作记录',
                        'group' => 'system',
                        'sortOrder' => 30,
                    ],
                    'settings' => [
                        'class' => \App\DataTables\SettingDataTable::class,
                        'title' => '系统设置',
                        'group' => 'system',
                        'sortOrder' => 40,
                    ],
                ])
                
                // 从目录扫描注册（可选）
                ->registerDataTablesFromDirectory(
                    app_path('DataTables'),
                    'App\\DataTables',
                    [
                        'suffix' => 'DataTable',
                        'recursive' => true,
                        'exclude' => ['AbstractDataTable.php', 'BaseDataTable.php'],
                    ]
                )
                
                ->build()
        );
    }

    /**
     * 自定义启动
     */
    public function customBoot(): void
    {
        // 注册路由
        $this->loadRoutes();
        
        // 注册命令（如果有的话）
        if ($this->app->runningInConsole()) {
            $this->commands([
                // \App\Console\Commands\ShowcaseListCommand::class,
                // \App\Console\Commands\ShowcaseMakeDataTableCommand::class,
            ]);
        }
    }

    /**
     * 加载路由
     */
    protected function loadRoutes(): void
    {
        $showcase = $this->app->make(\Dybasedev\LunaPrototype\Showcase\LunaShowcase::class);

        // 方式1：使用 Showcase 提供的所有路由
        $this->app['router']->group([
            'prefix' => 'api/showcase',
            'middleware' => ['api', 'auth:sanctum'],
        ], $showcase->routes());
        
        // 方式2：只使用 DataTable 路由
        // $this->app['router']->group([
        //     'prefix' => 'api/data-tables',
        //     'middleware' => ['api', 'auth:sanctum'],
        // ], $showcase->dataTable()->routes());
    }
}