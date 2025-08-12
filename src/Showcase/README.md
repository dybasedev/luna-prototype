# Showcase 组件

Showcase 是 LunaPrototype 的 UI 组件抽象层，提供了一套完整的前后端界面交互解决方案。通过 Showcase，后端开发者可以使用统一的方式描述前端界面，实现配置驱动的界面生成。

## 核心功能

- **DataTable 数据表格**：标准化的数据列表管理，支持搜索、排序、过滤、分页
- **RemoteSchema 表单结构**：动态的表单结构描述，支持多种场景的表单生成
- **UI 组件抽象**：统一的组件描述语言，支持多种前端框架
- **自动路由生成**：一行代码生成完整的 CRUD API
- **配置驱动**：通过配置而非代码实现界面定制
- **权限集成**：内置的权限控制机制

## 快速开始

### 1. 创建 DataTable

创建一个继承自 `DataTable` 或 `CrudDataTable` 的类：

```php
<?php

namespace App\DataTables;

use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * @title 用户管理
 * @description 管理系统用户
 * @group system
 */
class UserDataTable extends CrudDataTable
{
    protected function model(): string
    {
        return \App\Models\User::class;
    }

    public function columns(Request $request): array
    {
        return [
            UI::column('id')->title('ID')->sortable(true),
            UI::column('name')->title('姓名')->searchable(true),
            UI::column('email')->title('邮箱')->searchable(true),
            UI::column('created_at')->title('创建时间')->type('dateTime'),
        ];
    }

    public function query(Request $request): Builder
    {
        return $this->model()::query();
    }
}
```

### 2. 注册 DataTable

在服务提供者中注册（继承 `LunaServiceProvider`）：

```php
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;

class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        $this->registerModule(
            LunaShowcaseConfigure::create()
                ->registerDataTablesFromDirectory(
                    app_path('DataTables'),
                    'App\\DataTables'
                )
                ->build()
        );
    }
}
```

### 3. 路由接入

DataTable 不提供默认路由，您需要在控制器中手动接入。以下是几种常见的接入方式：

#### 方式一：完整的控制器实现

```php
use Dybasedev\LunaPrototype\Showcase\LunaShowcase;
use Illuminate\Http\Request;
use function ok;

class DataTableController extends Controller
{
    public function __construct(
        private LunaShowcase $showcase
    ) {}

    /**
     * 获取所有 DataTable 列表
     */
    public function index(Request $request)
    {
        $dataTables = $this->showcase->dataTable()->all(
            $request->input('group')
        );
        
        return ok([
            'items' => $dataTables,
            'groups' => $this->showcase->dataTable()->groups(),
        ]);
    }

    /**
     * 获取表格元数据
     */
    public function meta($key, Request $request)
    {
        $result = $this->showcase->dataTable()->handleRequest($key, 'meta', $request);
        return ok($result);
    }

    /**
     * 获取数据列表或单条记录
     */
    public function show($key, Request $request)
    {
        // 如果有 id 参数，返回单条记录
        if ($request->has('id')) {
            $result = $this->showcase->dataTable()->handleRequest($key, 'find', $request);
            return ok($result);
        }
        
        // 否则返回列表
        $result = $this->showcase->dataTable()->handleRequest($key, 'list', $request);
        return ok([
            'list' => $result->items(),
            'total' => $result->total(),
            'current' => $result->currentPage(),
            'pageSize' => $result->perPage(),
        ]);
    }

    /**
     * 创建记录
     */
    public function store($key, Request $request)
    {
        $result = $this->showcase->dataTable()->handleRequest($key, 'create', $request);
        return ok($result, null, '创建成功');
    }

    /**
     * 更新记录
     */
    public function update($key, Request $request)
    {
        $result = $this->showcase->dataTable()->handleRequest($key, 'update', $request);
        return ok($result, null, '更新成功');
    }

    /**
     * 删除记录
     */
    public function destroy($key, Request $request)
    {
        $this->showcase->dataTable()->handleRequest($key, 'delete', $request);
        return ok(null, null, '删除成功');
    }

    /**
     * 批量删除
     */
    public function batchDelete($key, Request $request)
    {
        $count = $this->showcase->dataTable()->handleRequest($key, 'batch-delete', $request);
        return ok(['count' => $count], null, "成功删除 {$count} 条记录");
    }

    /**
     * 导出数据
     */
    public function export($key, Request $request)
    {
        return $this->showcase->dataTable()->handleRequest($key, 'export', $request);
    }
}
```

然后在路由文件中注册：

```php
Route::prefix('api/data-tables')->middleware(['auth'])->group(function () {
    Route::get('/', [DataTableController::class, 'index']);
    Route::get('{key}/meta', [DataTableController::class, 'meta']);
    Route::get('{key}', [DataTableController::class, 'show']);
    Route::post('{key}', [DataTableController::class, 'store']);
    Route::put('{key}', [DataTableController::class, 'update']);
    Route::delete('{key}', [DataTableController::class, 'destroy']);
    Route::post('{key}/batch-delete', [DataTableController::class, 'batchDelete']);
    Route::post('{key}/export', [DataTableController::class, 'export']);
});
```

#### 方式二：使用查询参数的简化路由

```php
Route::prefix('api/data-table')->middleware(['auth'])->group(function () {
    Route::any('{key?}', function ($key = null, Request $request, LunaShowcase $showcase) {
        // 如果没有 key，返回列表
        if (!$key) {
            return ok([
                'items' => $showcase->dataTable()->all($request->input('group')),
                'groups' => $showcase->dataTable()->groups(),
            ]);
        }

        // 根据请求方法和参数决定操作
        $action = match ($request->method()) {
            'GET' => $request->has('id') ? 'find' : ($request->input('action') ?? 'list'),
            'POST' => $request->input('action', 'create'),
            'PUT' => 'update',
            'DELETE' => 'delete',
            default => throw new \Exception('Unsupported method'),
        };

        $result = $showcase->dataTable()->handleRequest($key, $action, $request);

        // 格式化返回结果
        return match ($action) {
            'list' => ok([
                'list' => $result->items(),
                'total' => $result->total(),
                'current' => $result->currentPage(),
                'pageSize' => $result->perPage(),
            ]),
            'meta' => ok($result),
            'find' => ok($result),
            'create' => ok($result, null, '创建成功'),
            'update' => ok($result, null, '更新成功'),
            'delete' => ok(null, null, '删除成功'),
            'batch-delete' => ok(['count' => $result], null, "成功删除 {$result} 条记录"),
            'export' => $result,
            default => ok($result),
        };
    });
});
```

#### 方式三：为特定 DataTable 创建独立控制器

对于重要的 DataTable，可以创建独立的控制器：

```php
class UserController extends Controller
{
    public function __construct(
        private LunaShowcase $showcase
    ) {}

    public function index(Request $request)
    {
        // 可以添加额外的业务逻辑
        if (!$request->user()->hasRole('admin')) {
            $request->merge(['filter' => ['department_id' => $request->user()->department_id]]);
        }
        
        $result = $this->showcase->dataTable()->handleRequest('users', 'list', $request);
        
        return ok([
            'list' => $result->items(),
            'total' => $result->total(),
            'current' => $result->currentPage(),
            'pageSize' => $result->perPage(),
        ]);
    }

    public function store(Request $request)
    {
        // 添加自定义验证
        $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);
        
        // 处理密码加密
        $request->merge([
            'password' => bcrypt($request->password)
        ]);
        
        $user = $this->showcase->dataTable()->handleRequest('users', 'create', $request);
        
        // 发送欢迎邮件
        Mail::to($user->email)->send(new WelcomeMail($user));
        
        return ok($user, null, '用户创建成功');
    }
    
    // 其他方法...
}
```

### 4. 与权限系统集成

在控制器中添加权限检查：

```php
class SecureDataTableController extends Controller
{
    public function __construct(
        private LunaShowcase $showcase
    ) {}

    /**
     * 检查 DataTable 访问权限
     */
    protected function checkPermission(string $key, string $action, Request $request): void
    {
        $dataTable = $this->showcase->dataTable()->get($key);
        
        // 获取 DataTable 的权限属性
        $reflection = new \ReflectionClass($dataTable);
        $attributes = $reflection->getAttributes(\Dybasedev\LunaPrototype\Showcase\Attributes\Permission::class);
        
        if (!empty($attributes)) {
            $permission = $attributes[0]->newInstance();
            $requiredPermissions = $permission->getPermissions();
            
            if ($permission->requireAll) {
                // 需要所有权限
                foreach ($requiredPermissions as $perm) {
                    if (!$request->user()->can($perm)) {
                        abort(403, '权限不足');
                    }
                }
            } else {
                // 需要任意一个权限
                $hasPermission = false;
                foreach ($requiredPermissions as $perm) {
                    if ($request->user()->can($perm)) {
                        $hasPermission = true;
                        break;
                    }
                }
                if (!$hasPermission) {
                    abort(403, '权限不足');
                }
            }
        }
        
        // 检查操作级别的权限
        $actionPermissions = [
            'create' => 'create-' . $key,
            'update' => 'update-' . $key,
            'delete' => 'delete-' . $key,
            'export' => 'export-' . $key,
        ];
        
        if (isset($actionPermissions[$action])) {
            if (!$request->user()->can($actionPermissions[$action])) {
                abort(403, "没有{$action}权限");
            }
        }
    }

    public function show($key, Request $request)
    {
        $this->checkPermission($key, 'view', $request);
        
        if ($request->has('id')) {
            $result = $this->showcase->dataTable()->handleRequest($key, 'find', $request);
            return ok($result);
        }
        
        $result = $this->showcase->dataTable()->handleRequest($key, 'list', $request);
        return ok([
            'list' => $result->items(),
            'total' => $result->total(),
            'current' => $result->currentPage(),
            'pageSize' => $result->perPage(),
        ]);
    }

    public function store($key, Request $request)
    {
        $this->checkPermission($key, 'create', $request);
        
        $result = $this->showcase->dataTable()->handleRequest($key, 'create', $request);
        return ok($result, null, '创建成功');
    }

    // 其他方法类似...
}
```

## DataTable 详解

### 基础 DataTable

只读数据表格，适用于日志、报表等场景：

```php
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTable;

abstract class LogDataTable extends DataTable
{
    public function columns(Request $request): array
    {
        return [
            UI::column('level')->title('级别')
                ->type('badge')
                ->properties([
                    'valueEnum' => [
                        'info' => ['text' => 'INFO', 'status' => 'processing'],
                        'error' => ['text' => 'ERROR', 'status' => 'error'],
                    ]
                ]),
            UI::column('message')->title('消息')
                ->searchable(true)
                ->ellipsis(true),
        ];
    }

    public function query(Request $request): Builder
    {
        return Log::query()->orderBy('created_at', 'desc');
    }
}
```

### CRUD DataTable

支持完整增删改查操作：

```php
use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;

abstract class ProductDataTable extends CrudDataTable
{
    protected function model(): string
    {
        return Product::class;
    }

    // 验证规则
    protected function createRules(Request $request): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ];
    }

    // 数据预处理
    protected function prepareCreateData(Request $request): array
    {
        $data = $request->only(['name', 'price', 'description']);
        $data['user_id'] = auth()->id();
        return $data;
    }

    // 生命周期钩子
    protected function afterCreate(Model $model, Request $request): void
    {
        // 发送通知、记录日志等
    }
}
```

### 高级功能

#### 1. 搜索和过滤

在 `query()` 方法中实现所有查询逻辑：

```php
use Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper;

public function columns(Request $request): array
{
    return [
        // 文本搜索
        UI::column('name')->title('名称')->searchable(true),
        
        // 下拉过滤
        UI::column('status')->title('状态')
            ->properties([
                'filters' => [
                    ['text' => '启用', 'value' => 'active'],
                    ['text' => '禁用', 'value' => 'inactive'],
                ]
            ]),
        
        // 日期范围过滤
        UI::column('created_at')->title('创建时间')
            ->type('dateRange')
            ->hidden(true) // 仅用于搜索
            ->searchable(true),
    ];
}

public function query(Request $request): Builder
{
    $query = $this->model()::query();
    
    // 使用 QueryHelper 辅助方法
    $query->when(...QueryHelper::searchLike($request, ['name', 'email']));
    
    // 状态过滤
    $query->when(...QueryHelper::applyCondition($request, 'status', 'filters.status'));
    
    // 日期范围过滤
    $query->when(...QueryHelper::dateBetween($request, 'created_at', 'filters.created_at'));
    
    // 排序
    $query->when(...QueryHelper::applySorter($request));
    
    return $query;
}
```

#### 2. 数据转换

```php
public function mapListRecord(mixed $record, Request $request): mixed
{
    return [
        'id' => $record->id,
        'name' => $record->name,
        'price' => number_format($record->price, 2),
        'status_text' => $record->status_label, // 访问器
        'can_edit' => $request->user()->can('update', $record),
    ];
}
```

#### 3. 批量操作

```php
protected function getBatchActions(Request $request): array
{
    return [
        [
            'key' => 'activate',
            'label' => '批量激活',
            'type' => 'primary',
        ],
        [
            'key' => 'delete',
            'label' => '批量删除',
            'type' => 'danger',
            'confirm' => '确定要删除选中的记录吗？',
        ],
    ];
}

// 处理批量操作
public function handleBatchAction(string $action, array $ids, Request $request): mixed
{
    return match($action) {
        'activate' => $this->batchActivate($ids),
        'delete' => $this->batchDelete($request),
        default => throw new \InvalidArgumentException("Unknown action: {$action}"),
    };
}
```

#### 4. 导出功能

```php
public function export(Request $request): mixed
{
    $data = $this->query($request)->get();
    
    return Excel::download(new UsersExport($data), 'users.xlsx');
}
```

## 配置选项

### 注册方式

#### 1. 手动注册

```php
$configure->registerDataTable('users', UserDataTable::class, [
    'title' => '用户管理',
    'description' => '管理系统用户',
    'group' => 'system',
    'sortOrder' => 10,
]);
```

#### 2. 批量注册

```php
$configure->registerDataTables([
    'users' => [
        'class' => UserDataTable::class,
        'title' => '用户管理',
        'group' => 'system',
    ],
    'roles' => RoleDataTable::class, // 简化形式
]);
```

#### 3. 目录扫描

```php
$configure->registerDataTablesFromDirectory(
    directory: app_path('DataTables'),
    namespace: 'App\\DataTables',
    options: [
        'suffix' => 'DataTable',
        'recursive' => true,
        'exclude' => ['AbstractDataTable.php'],
        'pattern' => '/^[A-Z]\w+DataTable\.php$/',
        'keyGenerator' => fn($class) => Str::snake(class_basename($class)),
        'metaGenerator' => fn($class) => ['group' => 'default'],
    ]
);
```

### 元数据注解

使用 PHP 8 属性（Attributes）配置 DataTable：

```php
use Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta;
use Dybasedev\LunaPrototype\Showcase\Attributes\Permission;
use Dybasedev\LunaPrototype\Showcase\Attributes\Route;

#[DataTableMeta(
    title: '订单管理',
    description: '管理商城订单',
    group: 'shop',
    sortOrder: 100
)]
#[Permission(['manage-orders', 'view-orders'], requireAll: false)]
#[Route(
    prefix: 'orders',
    except: ['export'],
    middleware: ['auth:admin']
)]
class OrderDataTable extends CrudDataTable
{
    // ...
}
```

### 可用的属性类

#### DataTableMeta
配置 DataTable 的基本信息：
- `title` - 标题
- `description` - 描述
- `group` - 分组
- `sortOrder` - 排序顺序
- `visible` - 是否可见

#### Permission
配置权限要求：
- `permissions` - 权限名称或数组
- `guard` - 守卫名称
- `requireAll` - 是否需要所有权限

#### Route
配置路由行为：
- `prefix` - 路由前缀
- `only` - 只启用的操作
- `except` - 排除的操作
- `middleware` - 中间件


### 服务提供者配置

在您的项目中创建服务提供者，继承 `LunaServiceProvider`：

```php
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;

class ShowcaseServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        $this->registerModule(
            LunaShowcaseConfigure::create()
                ->setDefaultAdapter('ant-design-pro')
                ->registerDataTablesFromDirectory(
                    app_path('DataTables'),
                    'App\\DataTables'
                )
                ->build()
        );
    }
}
```

## UI 组件

### Column（列）

```php
UI::column('name')->title('名称')
    ->name('user_name')           // 数据字段名
    ->type('text')                // 值类型
    ->tooltip('提示信息')         // 提示
    ->searchable(true)            // 可搜索
    ->sortable(true)              // 可排序
    ->width(200)                  // 宽度
    ->ellipsis(true)              // 文本省略
    ->copyable(true)              // 可复制
    ->hidden(false)               // 隐藏
    ->readonly(false)             // 只读
    ->properties([                // 扩展属性
        'valueEnum' => [...],     // 枚举值
        'filters' => [...],       // 过滤器
        'fixed' => 'left',        // 固定位置
    ]);
```

### Field（字段）

```php
UI::field('name')->title('名称')
    ->type('text')                // 输入类型
    ->placeholder('请输入')       // 占位符
    ->tooltip('帮助信息')         // 提示
    ->readonly(false)             // 只读
    ->hidden(false)               // 隐藏
    ->width('md')                 // 宽度
    ->properties([                // 扩展属性
        'defaultValue' => '',     // 默认值
        'rules' => ['required'],  // 验证规则
        'disabled' => false,      // 禁用
        'options' => [...],       // 选项（用于 select 等）
    ]);
```

### 值类型

支持的 `valueType`：
- `text` - 文本
- `number` - 数字
- `money` - 金额
- `date` - 日期
- `dateTime` - 日期时间
- `dateRange` - 日期范围
- `time` - 时间
- `select` - 下拉选择
- `checkbox` - 复选框
- `radio` - 单选框
- `switch` - 开关
- `rate` - 评分
- `progress` - 进度条
- `percent` - 百分比
- `code` - 代码
- `avatar` - 头像
- `image` - 图片
- `jsonCode` - JSON
- `password` - 密码
- `color` - 颜色
- `badge` - 徽标
- `tag` - 标签
- `link` - 链接
- `option` - 操作按钮

## 适配器

Showcase 通过适配器模式支持不同的前端框架：

### 内置适配器

- `AntDesignProAdapter` - Ant Design Pro 组件

### 自定义适配器

```php
use Dybasedev\LunaPrototype\Showcase\Adapter;

class ElementPlusAdapter extends Adapter
{
    public function column(Column $column): array
    {
        // 转换为 Element Plus 的列配置
        return [
            'prop' => $column->name,
            'label' => $column->title,
            'sortable' => $column->sortable ? 'custom' : false,
            'width' => $column->width,
            'showOverflowTooltip' => $column->ellipsis,
            // ...
        ];
    }

    public function field(Field|FieldGroup $field, string|array|null $prefix = null): array
    {
        // 转换为 Element Plus 的表单字段配置
        $output = [
            'prop' => $field->name,
            'label' => $field->title,
            'type' => $this->mapFieldType($field->type),
        ];
        
        if ($field instanceof FieldGroup) {
            $output['children'] = array_map(
                fn($child) => $this->field($child, $field->name),
                $field->fields
            );
        }
        
        return $output;
    }
    
    protected function mapFieldType(string $type): string
    {
        return match($type) {
            'text' => 'input',
            'number' => 'input-number',
            'select' => 'select',
            'date' => 'date-picker',
            default => $type,
        };
    }
}

// 注册适配器
$configure->registerAdapter('element-plus', ElementPlusAdapter::class);
```

## 权限控制

### 全局权限

在 DataTable 中控制：

```php
public function authorized(): bool
{
    return auth()->user()->can('manage-users');
}
```

### 操作权限

```php
protected function getPermissions(Request $request): array
{
    $user = $request->user();
    
    return [
        'create' => $user->can('create-users'),
        'update' => $user->can('update-users'),
        'delete' => $user->can('delete-users'),
        'export' => $user->can('export-users'),
    ];
}
```

### 行级权限

```php
public function mapListRecord(mixed $record, Request $request): mixed
{
    return [
        // ... 其他字段
        '_permissions' => [
            'update' => $request->user()->can('update', $record),
            'delete' => $request->user()->can('delete', $record),
        ],
    ];
}
```

## 最佳实践

### 1. 目录结构

```
app/
├── DataTables/
│   ├── System/
│   │   ├── UserDataTable.php
│   │   └── RoleDataTable.php
│   ├── Shop/
│   │   ├── ProductDataTable.php
│   │   └── OrderDataTable.php
│   └── BaseDataTable.php  # 基类
```

### 2. 性能优化

```php
public function query(Request $request): Builder
{
    return $this->model()::query()
        ->with(['profile', 'roles'])     // 预加载关联
        ->select(['id', 'name', 'email']) // 只选择需要的字段
        ->when($request->has('fast'), fn($q) => 
            $q->limit(100)  // 快速模式限制数量
        );
}
```

### 3. 缓存使用

```php
public function list(Request $request): LengthAwarePaginator
{
    $cacheKey = 'datatable.users.' . md5(serialize($request->all()));
    
    return Cache::tags(['datatable', 'users'])->remember(
        $cacheKey,
        300, // 5分钟
        fn() => parent::list($request)
    );
}
```

### 4. 错误处理

```php
public function create(Request $request): mixed
{
    try {
        return parent::create($request);
    } catch (ValidationException $e) {
        throw LunaException::create('Validation failed')
            ->withDisplayMessage('数据验证失败')
            ->withData(['errors' => $e->errors()]);
    } catch (\Exception $e) {
        Log::error('Failed to create user', [
            'error' => $e->getMessage(),
            'data' => $request->all(),
        ]);
        
        throw LunaException::create('Creation failed')
            ->withDisplayMessage('创建失败，请稍后重试');
    }
}
```

## 前端集成

### Ant Design Pro Components

基于 Pro Components 的最佳实践：

#### 1. 封装通用 DataTable 组件

```typescript
import {
  ProTable,
  type ProColumns,
  type ProTableProps,
} from '@ant-design/pro-components';
import { useRequest } from 'alova/client';
import type { SortOrder } from 'antd/es/table/interface';
import { useCallback, useEffect } from 'react';

export type DataTableProps<
  DataType extends Record<string, any>,
  Params extends Record<string, any> = Record<string, any>
> = ProTableProps<DataType, Params> & {
  name: string;
  operation?: OperationBuilder;
  columnMapper?: ColumnMapperBuilder;
  onColumnUpdated?: (columns: ProColumns[]) => void;
};

const DataTable: React.FC<DataTableProps<any>> = ({
  name,
  columns,
  operation,
  columnMapper,
  onColumnUpdated,
  ...props
}) => {
  // 从后端获取列配置
  const { data: remoteColumns, send: fetchRemoteColumns } = useRequest(
    () => api.get(`/api/data-tables/${name}/meta`),
    { immediate: !columns }
  );

  // 请求数据
  const request = useCallback(
    async (params, sort, filter) => {
      const { current: page = 1, pageSize, keyword, ...query } = params;
      
      const response = await api.get(`/api/data-tables/${name}`, {
        params: {
          page,
          pageSize,
          keyword,
          sort,
          filters: filter,
          ...query,
        },
      });

      return {
        data: response.data.list,
        total: response.data.total,
        success: true,
      };
    },
    [name]
  );

  // 合并列配置
  let tableColumns = columns || remoteColumns?.columns;
  
  // 添加操作列
  if (operation && tableColumns) {
    tableColumns = [...tableColumns, operation.build()];
  }

  return (
    <ProTable
      rowKey="id"
      size="small"
      search={{
        defaultCollapsed: false,
      }}
      pagination={{
        showSizeChanger: true,
      }}
      {...props}
      request={request}
      columns={tableColumns || []}
    />
  );
};
```

#### 2. 操作列构建器

```typescript
export class OperationBuilder {
  private actions: Array<(record: any, index: number) => React.ReactNode> = [];
  private width?: number;
  private title = '操作';

  action(builder: (record: any, index: number) => React.ReactNode) {
    this.actions.push(builder);
    return this;
  }

  setWidth(width: number) {
    this.width = width;
    return this;
  }

  setTitle(title: string) {
    this.title = title;
    return this;
  }

  build(): ProColumns {
    return {
      key: 'operation',
      title: this.title,
      search: false,
      fixed: 'right',
      valueType: 'option',
      width: this.width,
      render: (_, record, index) => {
        return this.actions.map(action => action(record, index));
      },
    };
  }
}

// 使用示例
const operation = new OperationBuilder()
  .setWidth(180)
  .action((record) => (
    <a key="edit" onClick={() => handleEdit(record)}>
      编辑
    </a>
  ))
  .action((record) => (
    <Popconfirm
      key="delete"
      title="确定删除吗？"
      onConfirm={() => handleDelete(record)}
    >
      <a>删除</a>
    </Popconfirm>
  ));
```

#### 3. RemoteSchema 表单组件

```typescript
import { BetaSchemaForm, ModalForm } from '@ant-design/pro-components';
import { useRequest } from 'alova/client';

export type RemoteSchemaProps = {
  name: string;
  embed?: boolean;
  params?: Record<string, any>;
};

const RemoteSchema: React.FC<RemoteSchemaProps> = ({
  name,
  embed = true,
  params,
}) => {
  const layoutType = embed ? 'Embed' : 'Form';

  const { data: columns } = useRequest(
    () => api.get(`/api/remote-schema/${name}/fields`, { params }),
    { immediate: true }
  );

  return columns && (
    <BetaSchemaForm 
      layoutType={layoutType} 
      columns={columns}
    />
  );
};

// 在 ModalForm 中使用
const UserEditModal = ({ open, onOpenChange, record, onSuccess }) => {
  return (
    <ModalForm
      title={record ? '编辑用户' : '新建用户'}
      open={open}
      onOpenChange={onOpenChange}
      modalProps={{ destroyOnClose: true }}
      request={async () => {
        if (record?.id) {
          // 编辑模式，获取详情
          const res = await api.get(`/api/data-tables/users`, {
            params: { id: record.id }
          });
          return res.data;
        }
        return {};
      }}
      onFinish={async (values) => {
        if (record?.id) {
          await api.put(`/api/data-tables/users`, values, {
            params: { id: record.id }
          });
        } else {
          await api.post('/api/data-tables/users', values);
        }
        onSuccess?.();
        return true;
      }}
    >
      <RemoteSchema 
        name="user_form" 
        params={{ mode: record ? 'edit' : 'create' }}
      />
    </ModalForm>
  );
};
```

#### 4. 完整页面示例

```typescript
import { PageContainer } from '@ant-design/pro-components';
import { Button } from 'antd';
import { useState } from 'react';

const UserManagement = () => {
  const [modalVisible, setModalVisible] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);
  const tableRef = useRef();

  const handleEdit = (record) => {
    setCurrentRecord(record);
    setModalVisible(true);
  };

  const handleDelete = async (record) => {
    await api.delete(`/api/data-tables/users`, {
      params: { id: record.id }
    });
    tableRef.current?.reload();
  };

  const operation = new OperationBuilder()
    .action((record) => (
      <a key="edit" onClick={() => handleEdit(record)}>
        编辑
      </a>
    ))
    .action((record) => (
      <Popconfirm
        key="delete"
        title="确定删除吗？"
        onConfirm={() => handleDelete(record)}
      >
        <a>删除</a>
      </Popconfirm>
    ));

  return (
    <PageContainer>
      <DataTable
        name="users"
        actionRef={tableRef}
        operation={operation}
        toolBarRender={() => [
          <Button
            key="create"
            type="primary"
            onClick={() => {
              setCurrentRecord(null);
              setModalVisible(true);
            }}
          >
            新建用户
          </Button>,
        ]}
      />

      <UserEditModal
        open={modalVisible}
        onOpenChange={setModalVisible}
        record={currentRecord}
        onSuccess={() => {
          setModalVisible(false);
          tableRef.current?.reload();
        }}
      />
    </PageContainer>
  );
};
```

#### 5. 高级用法：自定义列映射

```typescript
// 处理特殊的列类型
const columnMapper = new ColumnMapperBuilder()
  // 处理日期时间
  .type('dateTime', (column) => ({
    ...column,
    render: (text) => text && dayjs(text).format('YYYY-MM-DD HH:mm:ss'),
    search: {
      transform: (value) => ({
        [column.dataIndex]: value && dayjs(value).format(),
      }),
    },
  }))
  // 处理特定列
  .column('status', (column) => ({
    ...column,
    valueEnum: {
      active: { text: '启用', status: 'Success' },
      inactive: { text: '禁用', status: 'Default' },
    },
  }));

// 使用
<DataTable 
  name="users" 
  columnMapper={columnMapper}
/>
```

#### 6. 使用 Hook 管理编辑器

```typescript
export function useDataTableEditor({
  recordKey,
  schema,
  onSubmit,
}) {
  const [editor, setEditor] = useState({
    open: false,
    record: undefined,
    mode: 'create',
  });

  const open = useCallback((option) => {
    setEditor({
      open: true,
      record: option?.record,
      mode: option?.mode ?? 'create',
    });
  }, []);

  const modal = (
    <ModalForm
      title={editor.mode === 'edit' ? '编辑' : '新建'}
      open={editor.open}
      onOpenChange={(open) => setEditor(prev => ({ ...prev, open }))}
      modalProps={{ destroyOnClose: true }}
      request={async () => {
        if (editor.mode === 'edit' && editor.record) {
          return await api.get(`/api/data-tables/${recordKey}`, {
            params: { id: editor.record.id }
          });
        }
        return {};
      }}
      onFinish={async (values) => {
        if (onSubmit) {
          return await onSubmit(values, editor.mode, editor.record);
        }
      }}
    >
      <RemoteSchema name={schema} params={{ mode: editor.mode }} />
    </ModalForm>
  );

  return { modal, open };
}

// 使用
const { modal, open } = useDataTableEditor({
  recordKey: 'users',
  schema: 'user_form',
  onSubmit: async (values, mode, record) => {
    if (mode === 'edit') {
      await api.put('/api/data-tables/users', values, {
        params: { id: record.id }
      });
    } else {
      await api.post('/api/data-tables/users', values);
    }
    tableRef.current?.reload();
    return true;
  },
});
```

## QueryHelper 辅助工具

`QueryHelper` 提供了一系列便捷的查询条件构建方法，专门设计用于配合 Laravel Builder 的 `when()` 方法使用。

所有方法都返回 `[condition, callback]` 格式的数组：
- `condition` - 布尔值，决定是否应用查询条件
- `callback` - 闭包，包含实际的查询逻辑

这样可以直接使用展开运算符 `...` 传递给 `when()` 方法：

```php
// QueryHelper 返回的格式
[true, fn($query) => $query->where('status', 'active')]

// 使用展开运算符相当于
$query->when(true, fn($query) => $query->where('status', 'active'));
```

### 可用方法

```php
use Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper;

// 基本用法：使用展开运算符直接在 when() 中传递参数

// 日期范围
$query->when(...QueryHelper::dateBetween($request, 'created_at'));

// 排序
$query->when(...QueryHelper::applySorter($request, ['name', 'created_at'])); // 限制允许的排序字段

// 通用条件
$query->when(...QueryHelper::applyCondition($request, 'status', 'filters.status'));

// 模糊搜索
$query->when(...QueryHelper::searchLike($request, ['name', 'email', 'phone']));

// 数值范围
$query->when(...QueryHelper::numberRange($request, 'price'));

// IN 条件
$query->when(...QueryHelper::whereIn($request, 'category_id'));

// 布尔值
$query->when(...QueryHelper::booleanValue($request, 'is_active'));

// 关联存在性
$query->when(...QueryHelper::hasRelation($request, 'orders'));
$query->when(...QueryHelper::doesntHaveRelation($request, 'inactive_users'));

// 带默认值的排序（如果没有排序参数，使用默认排序）
$query->when(
    ...QueryHelper::applySorter($request, ['name', 'created_at']),
    fn() => $query->latest() // 默认排序
);
```

### 完整示例

```php
class ProductDataTable extends CrudDataTable
{
    protected function model(): string
    {
        return Product::class;
    }
    
    public function query(Request $request): Builder
    {
        $query = $this->model()::query()->with(['category', 'brand']);
        
        // 文本搜索 - 多字段
        $query->when(...QueryHelper::searchLike($request, ['name', 'sku', 'description']));
        
        // 分类过滤
        $query->when(...QueryHelper::whereIn($request, 'category_id', 'filters.categories'));
        
        // 价格范围
        $query->when(...QueryHelper::numberRange($request, 'price'));
        
        // 上架状态
        $query->when(...QueryHelper::booleanValue($request, 'is_active', 'filters.is_active'));
        
        // 创建时间范围
        $query->when(...QueryHelper::dateBetween($request, 'created_at', 'filters.date_range'));
        
        // 排序（带默认值）
        $query->when(
            ...QueryHelper::applySorter($request, ['name', 'price', 'created_at']),
            fn() => $query->latest() // 默认排序
        );
        
        return $query;
    }
}
```

### 自定义查询逻辑

当 QueryHelper 不能满足需求时，直接使用 Laravel Builder：

```php
public function query(Request $request): Builder
{
    $query = $this->model()::query();
    
    // 复杂的业务逻辑
    if ($request->user()->isVip()) {
        $query->with('vipDiscounts');
    }
    
    // 自定义搜索逻辑
    if ($keyword = $request->input('keyword')) {
        $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhereHas('tags', fn($q) => $q->where('name', 'like', "%{$keyword}%"));
        });
    }
    
    // 使用原生 SQL
    if ($request->has('nearby')) {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $query->selectRaw("*, ST_Distance_Sphere(point(lng, lat), point(?, ?)) as distance", [$lng, $lat])
              ->having('distance', '<', 5000)
              ->orderBy('distance');
    }
    
    return $query;
}
```

## 常见问题

### Q: 如何处理复杂的业务逻辑？

在控制器中添加自定义逻辑：

```php
class OrderController extends Controller
{
    public function __construct(
        private LunaShowcase $showcase
    ) {}

    public function index(Request $request)
    {
        // 添加业务过滤条件
        if ($request->user()->role === 'merchant') {
            $request->merge([
                'filter' => ['merchant_id' => $request->user()->merchant_id]
            ]);
        }
        
        $result = $this->showcase->dataTable()->handleRequest('orders', 'list', $request);
        
        // 添加统计信息
        $stats = [
            'total_amount' => Order::where('merchant_id', $request->user()->merchant_id)->sum('amount'),
            'today_orders' => Order::whereDate('created_at', today())->count(),
        ];
        
        return ok([
            'list' => $result->items(),
            'total' => $result->total(),
            'stats' => $stats,
        ]);
    }

    public function updateStatus($id, Request $request)
    {
        // 自定义状态更新逻辑
        $order = Order::findOrFail($id);
        
        // 检查状态转换是否合法
        if (!$order->canTransitionTo($request->status)) {
            return error('无法转换到该状态');
        }
        
        $order->status = $request->status;
        $order->save();
        
        // 触发相关事件
        event(new OrderStatusChanged($order));
        
        return ok($order, null, '状态更新成功');
    }
}
```

### Q: 如何实现复杂的查询？

```php
public function query(Request $request): Builder
{
    $query = $this->model()::query();

    // 复杂的关联查询
    if ($request->has('with_stats')) {
        $query->withCount(['orders', 'reviews'])
              ->withSum('orders', 'total_amount');
    }

    // 子查询
    if ($request->has('active_only')) {
        $query->whereHas('orders', function ($q) {
            $q->where('created_at', '>', now()->subDays(30));
        });
    }

    return $query;
}
```

### Q: 如何实现数据导入？

扩展 DataTable 添加导入方法：

```php
public function import(Request $request): array
{
    $request->validate([
        'file' => 'required|file|mimes:csv,xlsx',
    ]);

    $import = new UsersImport();
    Excel::import($import, $request->file('file'));

    return [
        'success' => true,
        'imported' => $import->getRowCount(),
    ];
}
```

## RemoteSchema 表单结构

RemoteSchema 提供了动态表单结构描述功能，可以用于生成各种场景的表单，如 DataTable 的新增/编辑表单、配置页面等。

### 基础使用

创建一个基础的 RemoteSchema：

```php
use Dybasedev\LunaPrototype\Showcase\RemoteSchema\RemoteSchema;
use Dybasedev\LunaPrototype\Showcase\Attributes\RemoteSchemaMeta;

#[RemoteSchemaMeta(
    title: '用户表单',
    description: '用户信息管理',
    group: 'user',
    sortOrder: 10
)]
class UserFormSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '用户信息';
    }

    public function fields(Request $request): array
    {
        return [
            [
                'name' => 'username',
                'label' => '用户名',
                'type' => 'text',
                'required' => true,
                'rules' => 'required|string|min:3|max:20',
                'placeholder' => '请输入用户名',
            ],
            [
                'name' => 'email',
                'label' => '邮箱',
                'type' => 'email',
                'required' => true,
                'rules' => 'required|email',
            ],
            [
                'name' => 'role',
                'label' => '角色',
                'type' => 'select',
                'options' => [
                    ['value' => 'admin', 'label' => '管理员'],
                    ['value' => 'user', 'label' => '普通用户'],
                ],
            ],
        ];
    }
}
```

### 支持多模式的表单

为不同场景创建表单结构：

```php
class ProductFormSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '商品信息';
    }

    public function fields(Request $request): array
    {
        $mode = $request->input('mode', 'create');
        
        if ($mode === 'edit') {
            return $this->getEditFields($request);
        }
        
        return $this->getCreateFields($request);
    }
    
    protected function getCreateFields(Request $request): array
    {
        return [
            [
                'name' => 'name',
                'label' => '商品名称',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'price',
                'label' => '价格',
                'type' => 'number',
                'required' => true,
                'prefix' => '¥',
                'precision' => 2,
            ],
        ];
    }

    protected function getEditFields(Request $request): array
    {
        $fields = $this->getCreateFields($request);
        
        // 编辑时添加状态字段
        $fields[] = [
            'name' => 'status',
            'label' => '状态',
            'type' => 'select',
            'options' => [
                ['value' => 'active', 'label' => '在售'],
                ['value' => 'inactive', 'label' => '下架'],
            ],
        ];
        
        return $fields;
    }
}
```

### 配置页面

创建配置页面的表单结构：

```php
class SystemSettingsSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '系统设置';
    }
    
    public function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        // 添加分组信息
        $meta['groups'] = [
            ['key' => 'general', 'title' => '常规设置'],
            ['key' => 'email', 'title' => '邮件设置'],
        ];
        
        // 添加验证规则
        $meta['rules'] = [
            'site_name' => 'required|string|max:100',
            'mail_host' => 'required_if:mail_driver,smtp',
        ];
        
        return $meta;
    }

    public function fields(Request $request): array
    {
        return [
            [
                'name' => 'site_name',
                'label' => '网站名称',
                'type' => 'text',
                'group' => 'general',
                'required' => true,
            ],
            [
                'name' => 'mail_host',
                'label' => 'SMTP 主机',
                'type' => 'text',
                'group' => 'email',
            ],
        ];
    }
}
```

### 注册 RemoteSchema

```php
// 在服务提供者中
$configure->registerRemoteSchema('user_form', UserFormSchema::class);

// 批量注册
$configure->registerRemoteSchemas([
    'product_form' => ProductFormSchema::class,
    'system_settings' => SystemSettingsSchema::class,
]);
```

### 路由接入

RemoteSchema 不提供默认路由，您需要在控制器中手动接入：

```php
use Dybasedev\LunaPrototype\Showcase\LunaShowcase;
use Illuminate\Http\Request;
use function ok;

class RemoteSchemaController extends Controller
{
    public function __construct(
        private LunaShowcase $showcase
    ) {}

    /**
     * 获取所有表单列表
     */
    public function list(Request $request)
    {
        $schemas = $this->showcase->remoteSchema()->registry()->all(
            $request->input('group')
        );
        
        return ok($schemas);
    }

    /**
     * 获取表单字段结构
     */
    public function fields($id, Request $request)
    {
        $fields = $this->showcase->remoteSchema()->handleRequest(
            $id,
            'fields',
            $request
        );
        
        return ok($fields);
    }

    /**
     * 获取表单元数据
     */
    public function meta($id, Request $request)
    {
        $meta = $this->showcase->remoteSchema()->handleRequest(
            $id,
            'meta',
            $request
        );
        
        return ok($meta);
    }

    /**
     * 获取完整表单结构
     */
    public function schema($id, Request $request)
    {
        $schema = $this->showcase->remoteSchema()->handleRequest(
            $id,
            'schema',
            $request
        );
        
        return ok($schema);
    }
}
```

然后在路由文件中注册：

```php
Route::prefix('api/remote-schema')->group(function () {
    Route::get('list', [RemoteSchemaController::class, 'list']);
    Route::get('{id}/fields', [RemoteSchemaController::class, 'fields']);
    Route::get('{id}/meta', [RemoteSchemaController::class, 'meta']);
    Route::get('{id}/schema', [RemoteSchemaController::class, 'schema']);
});
```

或者使用查询参数的方式：

```php
Route::prefix('api/remote-schema')->group(function () {
    Route::get('/', function (Request $request, LunaShowcase $showcase) {
        $action = $request->input('action', 'list');
        
        return match($action) {
            'list' => ok($showcase->remoteSchema()->registry()->all($request->input('group'))),
            'fields', 'meta', 'schema' => ok(
                $showcase->remoteSchema()->handleRequest(
                    $request->input('id'),
                    $action,
                    $request
                )
            ),
            default => abort(404, 'Unknown action'),
        };
    });
});
```

### 与 DataTable 集成

您可以将 RemoteSchema 与 DataTable 结合使用，为数据表提供新增/编辑表单：

```php
class ProductController extends Controller
{
    public function __construct(
        private LunaShowcase $showcase
    ) {}

    /**
     * 获取商品列表
     */
    public function index(Request $request)
    {
        $result = $this->showcase->dataTable()->handleRequest('products', 'list', $request);
        
        return ok([
            'list' => $result->items(),
            'total' => $result->total(),
            'current' => $result->currentPage(),
            'pageSize' => $result->perPage(),
        ]);
    }

    /**
     * 获取新增/编辑表单结构
     */
    public function formSchema(Request $request)
    {
        // 根据是否有 id 参数决定模式
        $request->merge([
            'mode' => $request->has('id') ? 'edit' : 'create'
        ]);
        
        $schema = $this->showcase->remoteSchema()->handleRequest(
            'product_form',
            'schema',
            $request
        );
        
        return ok($schema);
    }

    /**
     * 创建商品
     */
    public function store(Request $request)
    {
        // 获取表单验证规则
        $schema = $this->showcase->remoteSchema()->get('product_form');
        $meta = $schema->meta($request);
        
        if (isset($meta['rules'])) {
            $request->validate($meta['rules'], $meta['messages'] ?? []);
        }
        
        $result = $this->showcase->dataTable()->handleRequest('products', 'create', $request);
        
        return ok($result, null, '创建成功');
    }
}
```

### 动态表单处理

对于需要根据条件动态调整的表单：

```php
class DynamicFormSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '动态表单';
    }

    public function fields(Request $request): array
    {
        $fields = [
            [
                'name' => 'type',
                'label' => '类型',
                'type' => 'select',
                'options' => [
                    ['value' => 'personal', 'label' => '个人'],
                    ['value' => 'company', 'label' => '企业'],
                ],
                'required' => true,
            ],
        ];

        // 根据类型动态添加字段
        $type = $request->input('values.type', $request->input('type'));
        
        if ($type === 'personal') {
            $fields[] = [
                'name' => 'id_number',
                'label' => '身份证号',
                'type' => 'text',
                'required' => true,
            ];
        } else if ($type === 'company') {
            $fields[] = [
                'name' => 'business_license',
                'label' => '营业执照号',
                'type' => 'text',
                'required' => true,
            ];
            $fields[] = [
                'name' => 'company_name',
                'label' => '公司名称',
                'type' => 'text',
                'required' => true,
            ];
        }

        return $fields;
    }
}
```

### 字段类型

支持的字段类型：
- `text` - 文本输入
- `textarea` - 多行文本
- `number` - 数字输入
- `email` - 邮箱输入
- `password` - 密码输入
- `select` - 下拉选择
- `radio` - 单选框
- `checkbox` - 复选框
- `switch` - 开关
- `date` - 日期选择
- `datetime` - 日期时间选择
- `time` - 时间选择
- `upload` - 文件上传
- `tags` - 标签输入
- `color` - 颜色选择

### Pro Components 高级特性

#### 1. BetaSchemaForm 的强大功能

```typescript
// BetaSchemaForm 支持的列配置
const columns: ProFormColumnsType[] = [
  {
    title: '用户名',
    dataIndex: 'username',
    valueType: 'text',
    formItemProps: {
      rules: [{ required: true, message: '请输入用户名' }],
    },
  },
  {
    title: '角色',
    dataIndex: 'role',
    valueType: 'select',
    request: async () => {
      // 动态加载选项
      const res = await api.get('/api/roles');
      return res.data.map(item => ({
        label: item.name,
        value: item.id,
      }));
    },
    dependencies: ['department'], // 依赖其他字段
    params: { departmentId: '{{department}}' }, // 动态参数
  },
  {
    title: '权限',
    dataIndex: 'permissions',
    valueType: 'dependency', // 动态渲染
    name: ['role'], // 依赖字段
    columns: ({ role }) => {
      if (role === 'admin') {
        return [
          {
            title: '全部权限',
            dataIndex: 'all_permissions',
            valueType: 'switch',
          },
        ];
      }
      return [
        {
          title: '权限选择',
          dataIndex: 'permissions',
          valueType: 'checkbox',
          valueEnum: {
            read: '读取',
            write: '写入',
            delete: '删除',
          },
        },
      ];
    },
  },
];
```

#### 2. ProTable 高级搜索

```typescript
// 自定义搜索表单
<DataTable
  name="orders"
  search={{
    labelWidth: 'auto',
    span: 6,
    collapseRender: false,
    searchText: '查询',
    resetText: '重置',
    optionRender: (searchConfig, formProps, dom) => [
      ...dom.reverse(),
      <Button key="export" onClick={handleExport}>
        导出
      </Button>,
    ],
  }}
  // 自定义表单项
  beforeSearchSubmit={(params) => {
    // 转换搜索参数
    if (params.dateRange) {
      params.startDate = params.dateRange[0];
      params.endDate = params.dateRange[1];
      delete params.dateRange;
    }
    return params;
  }}
/>
```

#### 3. 批量操作和工具栏

```typescript
const [selectedRowKeys, setSelectedRowKeys] = useState([]);

<DataTable
  name="users"
  rowSelection={{
    selectedRowKeys,
    onChange: setSelectedRowKeys,
    alwaysShowAlert: true,
  }}
  tableAlertRender={({ selectedRowKeys, selectedRows }) => (
    <Space size={16}>
      <span>
        已选 {selectedRowKeys.length} 项
        <a style={{ marginInlineStart: 8 }} onClick={() => setSelectedRowKeys([])}>
          取消选择
        </a>
      </span>
    </Space>
  )}
  tableAlertOptionRender={() => (
    <Space size={16}>
      <Button onClick={() => handleBatchDelete(selectedRowKeys)}>
        批量删除
      </Button>
      <Button onClick={() => handleBatchExport(selectedRowKeys)}>
        批量导出
      </Button>
    </Space>
  )}
  toolBarRender={(action, { selectedRows }) => [
    <Button
      key="create"
      type="primary"
      onClick={() => handleCreate()}
    >
      新建
    </Button>,
    <Dropdown
      key="menu"
      menu={{
        items: [
          { key: 'import', label: '导入数据' },
          { key: 'export', label: '导出全部' },
          { key: 'template', label: '下载模板' },
        ],
        onClick: ({ key }) => handleMenuClick(key),
      }}
    >
      <Button>
        更多操作 <DownOutlined />
      </Button>
    </Dropdown>,
  ]}
/>
```

#### 4. 嵌套表格和展开行

```typescript
<DataTable
  name="categories"
  expandable={{
    expandedRowRender: (record) => (
      <DataTable
        name="products"
        params={{ categoryId: record.id }}
        search={false}
        toolBarRender={false}
        pagination={false}
      />
    ),
  }}
/>
```

#### 5. 编辑表格

```typescript
<ProTable
  columns={[
    {
      title: '名称',
      dataIndex: 'name',
      valueType: 'text',
    },
    {
      title: '价格',
      dataIndex: 'price',
      valueType: 'money',
    },
    {
      title: '操作',
      valueType: 'option',
      render: (_, record, index, action) => [
        <a
          key="edit"
          onClick={() => {
            action?.startEditable?.(record.id);
          }}
        >
          编辑
        </a>,
      ],
    },
  ]}
  editable={{
    type: 'multiple',
    onSave: async (rowKey, data) => {
      await api.put(`/api/products/${rowKey}`, data);
    },
    onChange: setEditableRowKeys,
  }}
/>
```

#### 6. 拖拽排序

```typescript
import { DragSortTable } from '@ant-design/pro-components';

<DragSortTable
  columns={columns}
  dataSource={dataSource}
  dragSortKey="sort"
  onDragSortEnd={(newDataSource) => {
    setDataSource(newDataSource);
    // 保存排序
    api.post('/api/sort', {
      ids: newDataSource.map(item => item.id),
    });
  }}
/>
```

### 高级特性

#### 字段依赖

```php
[
    'name' => 'mail_port',
    'label' => 'SMTP 端口',
    'type' => 'number',
    'dependsOn' => [
        'field' => 'mail_driver',
        'value' => 'smtp',
    ],
]
```

#### 动态数据源

```php
[
    'name' => 'category_id',
    'label' => '分类',
    'type' => 'select',
    'dataSource' => [
        'url' => '/api/categories',
        'valueField' => 'id',
        'labelField' => 'name',
    ],
]
```

#### 字段验证

```php
[
    'name' => 'email',
    'label' => '邮箱',
    'type' => 'email',
    'rules' => 'required|email|unique:users,email',
    'messages' => [
        'required' => '邮箱不能为空',
        'email' => '请输入有效的邮箱地址',
    ],
]
```

## 架构设计

### 模块化结构

Showcase 采用模块化设计，将不同功能独立管理：

```
LunaShowcase (主模块)
├── DataTableManager (DataTable 管理器)
│   ├── DataTableRegistry (注册表)
│   ├── DataTableInterface (接口)
│   └── 各种 DataTable 实现
├── RemoteSchemaManager (RemoteSchema 管理器)
│   ├── RemoteSchemaRegistry (注册表)
│   ├── RemoteSchemaInterface (接口)
│   └── 各种 RemoteSchema 实现
├── Adapter (适配器系统)
│   └── AntDesignProAdapter
└── Helpers (辅助工具)
    └── QueryHelper (查询辅助)
```

### 使用示例

```php
// 获取 DataTable 管理器
$dataTableManager = $showcase->dataTable();

// 获取特定的 DataTable
$userDataTable = $dataTableManager->get('users');

// 处理请求
$result = $dataTableManager->handleRequest('users', 'list', $request);

// 获取所有 DataTable
$allDataTables = $dataTableManager->all();
```

## 总结

Showcase 组件通过提供标准化的 DataTable 和 RemoteSchema 抽象，以及灵活的配置系统，极大地简化了后台管理界面的开发。其核心优势包括：

1. **快速开发**：通过继承和配置快速创建功能完整的数据管理界面和表单结构
2. **标准化**：统一的接口和行为，降低学习成本
3. **可扩展**：丰富的钩子和扩展点，满足各种定制需求
4. **前端友好**：生成标准的 API 和元数据，便于前端集成
5. **模块化设计**：清晰的职责分离，便于维护和扩展
6. **动态表单**：RemoteSchema 提供了灵活的表单结构描述能力

通过 Showcase，您可以将更多精力放在业务逻辑上，而不是重复的 CRUD 和表单开发上。