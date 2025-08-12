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

### Ant Design Pro Components 接口响应结构

Showcase 组件生成的 API 响应结构直接对应 Ant Design Pro Components 的数据格式要求，特别是 ProTable 的 columns 配置和 SchemaForm 的字段结构。

#### 1. DataTable 列表响应结构

后端 DataTable 的 `list` 操作返回的数据结构：

```json
{
  "list": [          // ProTable 的 dataSource
    {
      "id": 1,
      "name": "张三",
      "email": "zhangsan@example.com",
      "created_at": "2024-01-01 12:00:00"
    }
  ],
  "total": 100,      // 总记录数，用于分页
  "current": 1,      // 当前页码
  "pageSize": 20    // 每页大小
}
```

这个结构直接对应 ProTable 的 `request` 返回格式要求。

#### 2. DataTable Meta 响应结构

后端 DataTable 的 `meta` 操作返回的列配置：

```json
{
  "columns": [
    {
      "dataIndex": "name",        // 对应 ProTable columns 的 dataIndex
      "title": "姓名",             // 对应 ProTable columns 的 title
      "valueType": "text",        // 对应 ProTable columns 的 valueType
      "search": true,             // 是否可搜索
      "sorter": true,             // 是否可排序
      "width": 150,               // 列宽度
      "ellipsis": true,           // 文本溢出省略
      "copyable": true,           // 是否可复制
      "hideInTable": false,       // 是否在表格中隐藏
      "hideInSearch": false,      // 是否在搜索表单中隐藏
      "hideInForm": false,        // 是否在表单中隐藏
      "formItemProps": {          // 表单项属性
        "rules": [
          { "required": true, "message": "请输入姓名" }
        ]
      },
      "fieldProps": {             // 字段属性
        "placeholder": "请输入姓名"
      }
    },
    {
      "dataIndex": "status",
      "title": "状态",
      "valueType": "select",
      "valueEnum": {              // 枚举值，用于 select、radio 等
        "active": { "text": "启用", "status": "Success" },
        "inactive": { "text": "禁用", "status": "Default" }
      },
      "filters": [                // 表格筛选器
        { "text": "启用", "value": "active" },
        { "text": "禁用", "value": "inactive" }
      ]
    },
    {
      "dataIndex": "created_at",
      "title": "创建时间",
      "valueType": "dateTime",    // 日期时间类型
      "sorter": true,
      "hideInForm": true,         // 在表单中隐藏
      "hideInSearch": false,
      "search": {
        "transform": (value) => ({ // 搜索值转换
          "created_at_start": value[0],
          "created_at_end": value[1]
        })
      }
    }
  ],
  "title": "用户管理",              // DataTable 标题
  "description": "管理系统用户",     // DataTable 描述
  "permissions": {                 // 权限配置
    "create": true,
    "update": true,
    "delete": true,
    "export": true
  },
  "batchActions": [                // 批量操作
    {
      "key": "delete",
      "label": "批量删除",
      "type": "danger",
      "confirm": "确定要删除选中的记录吗？"
    }
  ]
}
```

#### 3. RemoteSchema 表单字段响应结构

RemoteSchema 的 `fields` 操作返回的表单结构：

```json
{
  "columns": [      // BetaSchemaForm 的 columns 配置
    {
      "title": "基本信息",
      "valueType": "group",        // 分组类型
      "columns": [
        {
          "dataIndex": "username",
          "title": "用户名",
          "valueType": "text",
          "formItemProps": {
            "rules": [
              { "required": true, "message": "请输入用户名" },
              { "min": 3, "max": 20, "message": "用户名长度3-20个字符" }
            ]
          },
          "fieldProps": {
            "placeholder": "请输入用户名"
          }
        },
        {
          "dataIndex": "email",
          "title": "邮箱",
          "valueType": "email",
          "formItemProps": {
            "rules": [
              { "required": true, "message": "请输入邮箱" },
              { "type": "email", "message": "请输入有效的邮箱地址" }
            ]
          }
        }
      ]
    },
    {
      "dataIndex": "role",
      "title": "角色",
      "valueType": "select",
      "request": async () => {     // 动态请求选项
        // 返回选项数据
        return [
          { "label": "管理员", "value": "admin" },
          { "label": "普通用户", "value": "user" }
        ];
      },
      "dependencies": ["department"], // 依赖其他字段
      "params": {                      // 请求参数
        "departmentId": "{{department}}"
      }
    },
    {
      "dataIndex": "permissions",
      "title": "权限配置",
      "valueType": "dependency",    // 依赖渲染
      "name": ["role"],              // 依赖的字段
      "columns": ({ role }) => {     // 动态返回字段
        // 根据 role 值返回不同的字段配置
      }
    }
  ]
}
```

#### 4. 适配器转换逻辑

AntDesignProAdapter 负责将 Showcase 的 UI 组件描述转换为 Pro Components 需要的格式：

**Column 转换映射：**
- `UI::column()` 的配置 → ProTable 的 `columns` 配置
- `type` → `valueType`（text, number, date, select 等）
- `searchable` → `search: true/false`
- `sortable` → `sorter: true/false`
- `properties.valueEnum` → `valueEnum`（用于下拉选项）
- `properties.filters` → `filters`（表格筛选器）

**Field 转换映射：**
- `UI::field()` 的配置 → SchemaForm 的 `columns` 配置
- `type` → `valueType`
- `placeholder` → `fieldProps.placeholder`
- `rules` → `formItemProps.rules`
- `properties.options` → `valueEnum` 或 `request`

#### 5. 请求参数格式

ProTable 发送的请求参数格式：

```json
{
  "current": 1,           // 当前页
  "pageSize": 20,         // 每页大小
  "keyword": "搜索关键词",  // 搜索关键词
  "name": "张",           // 具体字段搜索
  "status": "active",     // 筛选条件
  "sorter": {             // 排序
    "field": "created_at",
    "order": "descend"
  },
  "filter": {             // 过滤条件
    "status": ["active", "pending"]
  }
}
```

后端通过 `QueryHelper` 处理这些参数：

```php
public function query(Request $request): Builder
{
    $query = $this->model()::query();
    
    // 处理搜索
    $query->when(...QueryHelper::searchLike($request, ['name', 'email']));
    
    // 处理筛选
    $query->when(...QueryHelper::applyCondition($request, 'status'));
    
    // 处理排序
    $query->when(...QueryHelper::applySorter($request));
    
    return $query;
}
```

#### 6. 数据流程说明

1. **初始化流程：**
   - 前端请求 `/api/data-tables/{key}/meta` 获取列配置
   - 后端返回 columns 结构，直接用于 ProTable 的 columns 属性

2. **数据加载流程：**
   - ProTable 自动发送请求到 `/api/data-tables/{key}`
   - 携带分页、搜索、排序参数
   - 后端返回符合 ProTable 格式的数据

3. **表单加载流程：**
   - 前端请求 `/api/remote-schema/{name}/fields`
   - 后端返回 SchemaForm 需要的 columns 结构
   - SchemaForm 自动渲染表单

4. **数据提交流程：**
   - SchemaForm 收集表单数据
   - 发送到对应的创建/更新接口
   - 后端处理并返回结果

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

### Pro Components 高级特性支持

#### 1. 后端支持的高级配置

Showcase 后端可以生成支持 Pro Components 高级特性的配置：

**动态字段依赖配置：**
```php
public function fields(Request $request): array
{
    return [
        UI::field('role')->title('角色')
            ->type('select')
            ->properties([
                'request' => '/api/roles',  // 动态加载选项的接口
                'dependencies' => ['department'], // 依赖字段
                'params' => ['departmentId' => '{{department}}'], // 动态参数
            ]),
        
        UI::field('permissions')->title('权限')
            ->type('dependency')  // 依赖类型
            ->properties([
                'name' => ['role'],  // 依赖的字段
                'columns' => [       // 根据条件返回不同配置
                    'admin' => [
                        ['dataIndex' => 'all_permissions', 'valueType' => 'switch']
                    ],
                    'default' => [
                        ['dataIndex' => 'permissions', 'valueType' => 'checkbox']
                    ]
                ]
            ])
    ];
}
```

**搜索表单配置：**
```php
public function meta(Request $request): array
{
    $meta = parent::meta($request);
    
    $meta['search'] = [
        'labelWidth' => 'auto',
        'span' => 6,
        'collapseRender' => false,
        'searchText' => '查询',
        'resetText' => '重置',
        'transform' => [  // 参数转换规则
            'dateRange' => ['startDate', 'endDate']
        ]
    ];
    
    return $meta;
}
```

#### 2. 批量操作支持

后端提供批量操作配置：

```php
protected function getBatchActions(Request $request): array
{
    return [
        [
            'key' => 'delete',
            'label' => '批量删除',
            'type' => 'danger',
            'confirm' => '确定要删除选中的记录吗？',
            'api' => '/api/data-tables/users/batch-delete'
        ],
        [
            'key' => 'export',
            'label' => '批量导出',
            'type' => 'default',
            'api' => '/api/data-tables/users/export'
        ]
    ];
}
```

前端接收到的响应结构：
```json
{
  "batchActions": [
    {
      "key": "delete",
      "label": "批量删除",
      "type": "danger",
      "confirm": "确定要删除选中的记录吗？",
      "api": "/api/data-tables/users/batch-delete"
    }
  ],
  "rowSelection": {
    "type": "checkbox",
    "alwaysShowAlert": true
  }
}
```

#### 3. 嵌套表格支持

后端提供嵌套表格的配置：

```php
public function meta(Request $request): array
{
    $meta = parent::meta($request);
    
    $meta['expandable'] = [
        'childDataTable' => 'products',  // 子表格的 DataTable key
        'params' => ['categoryId' => '{{id}}'],  // 传递给子表格的参数
        'hideSearch' => true,
        'hideToolBar' => true,
        'hidePagination' => true
    ];
    
    return $meta;
}
```

#### 4. 行内编辑支持

后端配置支持行内编辑：

```php
public function columns(Request $request): array
{
    return [
        UI::column('name')->title('名称')
            ->editable(true)  // 可编辑
            ->properties([
                'editableType' => 'text',
                'rules' => ['required' => true]
            ]),
        UI::column('price')->title('价格')
            ->type('money')
            ->editable(true)
            ->properties([
                'editableType' => 'number',
                'min' => 0
            ])
    ];
}

public function meta(Request $request): array
{
    $meta = parent::meta($request);
    
    $meta['editable'] = [
        'type' => 'multiple',  // single 或 multiple
        'actionRender' => true, // 显示保存和取消按钮
        'onSave' => '/api/data-tables/products/update'  // 保存接口
    ];
    
    return $meta;
}
```

#### 5. 拖拽排序支持

后端提供拖拽排序配置：

```php
public function meta(Request $request): array
{
    $meta = parent::meta($request);
    
    $meta['dragSort'] = [
        'enabled' => true,
        'key' => 'sort',  // 排序字段
        'api' => '/api/data-tables/products/sort'  // 排序保存接口
    ];
    
    return $meta;
}

// 处理排序请求
public function sort(Request $request): mixed
{
    $ids = $request->input('ids');
    
    foreach ($ids as $index => $id) {
        $this->model()::where('id', $id)
            ->update(['sort' => $index]);
    }
    
    return ['success' => true];
}
```

#### 6. 工具栏配置

后端提供工具栏按钮配置：

```php
public function meta(Request $request): array
{
    $meta = parent::meta($request);
    
    $meta['toolBar'] = [
        'actions' => [
            [
                'key' => 'create',
                'label' => '新建',
                'type' => 'primary',
                'icon' => 'plus',
                'action' => 'modal',  // modal, drawer, link
                'schema' => 'user_form'  // RemoteSchema key
            ],
            [
                'key' => 'import',
                'label' => '导入',
                'type' => 'default',
                'action' => 'upload',
                'api' => '/api/data-tables/users/import'
            ]
        ],
        'menu' => [  // 下拉菜单
            [
                'key' => 'export',
                'label' => '导出全部',
                'api' => '/api/data-tables/users/export'
            ],
            [
                'key' => 'template',
                'label' => '下载模板',
                'link' => '/api/templates/users.xlsx'
            ]
        ]
    ];
    
    return $meta;
}
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