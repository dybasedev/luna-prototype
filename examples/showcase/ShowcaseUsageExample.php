<?php

namespace Examples\Showcase;

use Dybasedev\LunaPrototype\Showcase\LunaShowcase;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Examples\Showcase\UserDataTable;
use Examples\Showcase\LogDataTable;
use Illuminate\Support\Facades\Route;

/**
 * Showcase 组件使用示例
 * 
 * 展示如何配置和使用 Showcase 组件
 */
class ShowcaseUsageExample
{
    /**
     * 示例1：基础配置
     */
    public function basicConfiguration()
    {
        // 创建配置
        $configure = LunaShowcaseConfigure::create()
            // 手动注册单个 DataTable
            ->registerDataTable('users', UserDataTable::class, [
                'title' => '用户管理',
                'description' => '管理系统用户',
                'group' => 'system',
                'sortOrder' => 1,
            ])
            // 注册另一个 DataTable
            ->registerDataTable('logs', LogDataTable::class, [
                'title' => '系统日志',
                'description' => '查看系统操作日志',
                'group' => 'system',
                'sortOrder' => 2,
            ])
            ->build();

        // 创建 Showcase 实例
        $showcase = new LunaShowcase($configure);

        // 使用 DataTable
        $dataTableManager = $showcase->dataTable();
        $usersDataTable = $dataTableManager->get('users');
        $result = $usersDataTable->list(request());

        return $result;
    }

    /**
     * 示例2：批量注册
     */
    public function batchRegistration()
    {
        $configure = LunaShowcaseConfigure::create()
            // 批量注册 DataTable
            ->registerDataTables([
                'users' => [
                    'class' => UserDataTable::class,
                    'title' => '用户管理',
                    'group' => 'system',
                ],
                'logs' => [
                    'class' => LogDataTable::class,
                    'title' => '日志管理',
                    'group' => 'system',
                ],
                'orders' => \App\DataTables\OrderDataTable::class, // 简单形式
            ])
            ->build();

        return new LunaShowcase($configure);
    }

    /**
     * 示例3：从目录自动扫描注册
     */
    public function directoryScanning()
    {
        $configure = LunaShowcaseConfigure::create()
            // 从目录扫描并注册所有 DataTable
            ->registerDataTablesFromDirectory(
                directory: app_path('DataTables'),
                namespace: 'App\\DataTables',
                options: [
                    'suffix' => 'DataTable',
                    'recursive' => true,
                    'exclude' => ['AbstractDataTable.php'],
                    'keyGenerator' => function ($className, $file) {
                        // 自定义键生成逻辑
                        $baseName = class_basename($className);
                        return strtolower(str_replace('DataTable', '', $baseName));
                    },
                ]
            )
            // 也可以扫描多个目录
            ->registerDataTablesFromDirectory(
                directory: app_path('Admin/DataTables'),
                namespace: 'App\\Admin\\DataTables',
                options: [
                    'metaGenerator' => function ($className, $file) {
                        // 自定义元数据生成
                        return [
                            'group' => 'admin',
                            'visible' => !str_contains($className, 'Internal'),
                        ];
                    },
                ]
            )
            ->build();

        return new LunaShowcase($configure);
    }

    /**
     * 示例4：在服务提供者中注册
     */
    public function serviceProviderRegistration()
    {
        // 在 AppServiceProvider 或专门的 ShowcaseServiceProvider 中
        return function ($app) {
            $app->singleton(LunaShowcase::class, function ($app) {
                $configure = LunaShowcaseConfigure::create()
                    // 手动注册 DataTable
                    ->registerDataTables([
                        'users' => \App\DataTables\UserDataTable::class,
                        'roles' => \App\DataTables\RoleDataTable::class,
                    ])
                    // 从目录扫描
                    ->registerDataTablesFromDirectory(
                        directory: app_path('DataTables'),
                        namespace: 'App\\DataTables'
                    )
                    // 注册自定义适配器
                    ->registerAdapter('element-plus', \App\Showcase\ElementPlusAdapter::class)
                    ->build();

                return new LunaShowcase($configure);
            });
        };
    }

    /**
     * 示例5：在控制器中使用
     */
    public function controllerUsage()
    {
        // 路由需要手动注册，不再提供默认路由
        Route::group(['prefix' => 'api/admin'], function () {
            // 参考 Showcase/README.md 文档了解如何手动注册路由
        });

        // 方式2：自定义控制器
        return new class {
            public function __construct(
                private LunaShowcase $showcase
            ) {}

            public function index($dataTable)
            {
                $dt = $this->showcase->dataTable()->get($dataTable);
                return $dt->list(request());
            }

            public function show($dataTable, $id)
            {
                $dt = $this->showcase->dataTable()->get($dataTable);
                return $dt->find($id, request());
            }

            public function store($dataTable)
            {
                $dt = $this->showcase->dataTable()->get($dataTable);
                return $dt->create(request());
            }

            public function update($dataTable, $id)
            {
                $dt = $this->showcase->dataTable()->get($dataTable);
                return $dt->update($id, request());
            }

            public function destroy($dataTable, $id)
            {
                $dt = $this->showcase->dataTable()->get($dataTable);
                return $dt->delete($id, request());
            }
        };
    }

    /**
     * 示例6：获取 DataTable 信息
     */
    public function dataTableInfo()
    {
        $showcase = app(LunaShowcase::class);

        // 获取 DataTable 管理器
        $dataTableManager = $showcase->dataTable();
        
        // 获取所有 DataTable
        $allDataTables = $dataTableManager->all();

        // 获取特定分组的 DataTable
        $systemDataTables = $dataTableManager->all('system');

        // 获取所有分组
        $groups = $dataTableManager->groups();

        // 检查 DataTable 是否存在
        $hasUsers = $dataTableManager->has('users');

        // 获取 DataTable 元数据
        $usersMeta = $dataTableManager->get('users')->meta(request());

        return [
            'all' => $allDataTables,
            'system' => $systemDataTables,
            'groups' => $groups,
            'hasUsers' => $hasUsers,
            'usersMeta' => $usersMeta,
        ];
    }

    /**
     * 示例7：带注解的 DataTable
     */
    public function annotatedDataTable()
    {
        // 使用 PHP 8 属性的 DataTable 类示例
        // 注意：属性应该放在类定义上，而不是方法上
        return new #[\Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta(
            title: '商品管理',
            description: '管理商城商品信息',
            group: 'shop',
            sortOrder: 10
        )] class extends \Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable {
            protected function model(): string
            {
                return \App\Models\Product::class;
            }

            public function columns(\Illuminate\Http\Request $request): array
            {
                return [
                    \Dybasedev\LunaPrototype\Showcase\UI::column('name', '商品名称'),
                    \Dybasedev\LunaPrototype\Showcase\UI::column('price', '价格'),
                    \Dybasedev\LunaPrototype\Showcase\UI::column('stock', '库存'),
                ];
            }

            public function query(\Illuminate\Http\Request $request): \Illuminate\Database\Eloquent\Builder
            {
                return $this->model()::query();
            }
        };
    }

    /**
     * 示例8：使用工厂函数注册
     */
    public function factoryRegistration()
    {
        $configure = LunaShowcaseConfigure::create()
            // 使用工厂函数，可以实现延迟加载或条件创建
            ->registerDataTable('dynamic', function () {
                // 可以根据条件返回不同的 DataTable
                if (auth()->user()->isAdmin()) {
                    return new \App\DataTables\AdminUserDataTable();
                } else {
                    return new \App\DataTables\BasicUserDataTable();
                }
            })
            ->build();

        return new LunaShowcase($configure);
    }
}