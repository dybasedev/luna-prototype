# Showcase Permission 集成

Showcase 组件提供了与 Permission 组件的可选集成，为 DataTable 添加权限控制功能。

## 核心特性

- **可选集成**：Permission 作为可选依赖，不影响 Showcase 独立使用
- **多层级权限**：支持表级、列级、行级、操作级权限控制
- **配置驱动**：通过 Builder 模式提供灵活的配置
- **自动降级**：Permission 组件不可用时正常工作
- **所有者过滤**：基于资源所有权的行级权限控制

## 快速开始

### 1. 配置集成

在 AppServiceProvider 中配置：

```php
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;

class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        $this->registerModule(
            LunaShowcaseConfigure::create()
                ->configurePermissionIntegration(
                    PermissionIntegrationBuilder::create()
                        ->withResourcePattern('admin.{key}')  // 资源命名模式
                        ->withOwnerFields('owner_type', 'owner_id')  // 所有者字段
                        ->enableOwnerFilter()  // 启用所有者过滤
                        ->mapResource('users', 'users')  // 自定义资源映射
                        ->mapResource('posts', 'content.posts')
                )
                ->registerDataTables([
                    'users' => UserDataTable::class,
                    'posts' => PostDataTable::class,
                ])
                ->build()
        );
    }
}
```

#### 最小化配置

如果使用默认设置，可以简化配置：

```php
$this->registerModule(
    LunaShowcaseConfigure::create()
        ->configurePermissionIntegration(
            PermissionIntegrationBuilder::create()
        )
        ->registerDataTables([
            'users' => UserDataTable::class,
        ])
        ->build()
);
```

#### 高级配置示例

```php
// 方式一：使用静态工厂方法
$permissionConfig = PermissionIntegrationBuilder::create()
    ->withResourcePattern('app.{key}')
    ->withOwnerFields('creator_type', 'creator_id')
    ->enableOwnerFilter()
    ->disableAutoCheck()
    ->mapResource('users', 'system.users')
    ->mapResource('posts', 'content.posts')
    ->mapResource('orders', 'shop.orders');

$this->registerModule(
    LunaShowcaseConfigure::create()
        ->configurePermissionIntegration($permissionConfig)
        ->registerDataTablesFromDirectory(
            app_path('DataTables'),
            'App\\DataTables'
        )
        ->build()
);

// 方式二：使用 new 实例化
$permissionConfig = new PermissionIntegrationBuilder();
$permissionConfig
    ->withResourcePattern('app.{key}')
    ->withOwnerFields('creator_type', 'creator_id')
    ->enableOwnerFilter();

$this->registerModule(
    LunaShowcaseConfigure::create()
        ->configurePermissionIntegration($permissionConfig)
        ->registerDataTables([/* ... */])
        ->build()
);
```

### 2. 在 DataTable 中使用

```php
use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionAwareDataTable;

class UserDataTable extends CrudDataTable
{
    use PermissionAwareDataTable;
    
    /**
     * 权限资源名称
     */
    protected ?string $permissionResource = 'admin.users';
    
    /**
     * 启用所有者过滤（只显示当前用户创建的记录）
     */
    protected bool $enableOwnerFilter = true;
    
    /**
     * 配置列权限（某些列需要特定权限才能查看）
     */
    protected array $columnPermissions = [
        'email' => 'view_email',  // 简单权限
        'phone' => [  // 复杂权限
            'action' => 'view_phone',
            'resource' => 'admin.users.sensitive'
        ],
        'balance' => 'view_financial',
    ];
    
    /**
     * 定义列（使用 defineColumns 而不是 columns）
     */
    protected function defineColumns(Request $request): array
    {
        return [
            UI::column('ID', 'id'),
            UI::column('姓名', 'name'),
            UI::column('邮箱', 'email'),  // 会根据权限自动过滤
            UI::column('电话', 'phone'),   // 会根据权限自动过滤
            UI::column('余额', 'balance'), // 会根据权限自动过滤
        ];
    }
    
    /**
     * 构建查询（使用 buildQuery 而不是 query）
     */
    protected function buildQuery(Request $request): Builder
    {
        return User::query()->with(['profile']);
    }
    
    /**
     * 定义操作按钮（会根据权限自动过滤）
     */
    protected function defineActions(Request $request): array
    {
        return [
            ['key' => 'create', 'label' => '新建'],
            ['key' => 'edit', 'label' => '编辑'],
            ['key' => 'delete', 'label' => '删除'],
        ];
    }
}
```

### 3. 在模型中使用 HasOwner

```php
use Dybasedev\LunaPrototype\Permission\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasOwner;
    
    // 可选：自定义所有者字段名
    protected string $ownerTypeKeyName = 'author_type';
    protected string $ownerIdKeyName = 'author_id';
    
    // 可选：提供额外的权限属性
    protected function getPermissionAttributes(): array
    {
        return [
            'status' => $this->status,
            'visibility' => $this->visibility,
            'published_at' => $this->published_at,
        ];
    }
}
```

## 配置选项

### PermissionIntegrationBuilder

构建器提供以下配置方法：

```php
// 方式一：使用静态工厂方法（推荐）
$builder = PermissionIntegrationBuilder::create()
    ->withResourcePattern('admin.{key}')
    ->withOwnerFields('owner_type', 'owner_id')
    ->enableOwnerFilter()
    ->disableAutoCheck()
    ->mapResource('users', 'users');

$configure->configurePermissionIntegration($builder);

// 方式二：使用 new 实例化
$builder = new PermissionIntegrationBuilder();
$builder
    ->withResourcePattern('admin.{key}')
    ->withOwnerFields('owner_type', 'owner_id')
    ->enableOwnerFilter();

$configure->configurePermissionIntegration($builder);

// 方式三：手动构建配置对象（用于需要复用配置的场景）
$config = PermissionIntegrationBuilder::create()
    ->withResourcePattern('admin.{key}')
    ->build();

$configure->withPermissionIntegration($config);
```

### PermissionIntegrationConfig

配置对象包含以下属性：

- `resourcePattern` - 资源命名模式，使用 `{key}` 作为占位符
- `defaultOwnerTypeField` - 默认所有者类型字段名
- `defaultOwnerIdField` - 默认所有者ID字段名
- `autoCheckPermission` - 是否自动检查权限
- `enableOwnerFilter` - 是否启用行级所有者过滤
- `resourceMappings` - DataTable 与权限资源的映射

## PermissionAwareDataTable Trait

这个 trait 为 DataTable 提供权限感知能力：

### 属性

- `$permissionResource` - 权限资源名称
- `$columnPermissions` - 列权限映射
- `$enableOwnerFilter` - 是否启用所有者过滤

### 方法覆盖

trait 会自动覆盖以下方法来集成权限功能：

- `getPermissions()` - 使用 Permission 组件检查权限，替代基于方法存在性的默认逻辑
- `authorized()` - 检查用户是否有访问 DataTable 的权限
- `create()` - 在创建前检查 create 权限
- `update()` - 在更新前检查 update 权限，如果启用所有者过滤还会检查资源所有权
- `delete()` - 在删除前检查 delete 权限，如果启用所有者过滤还会检查资源所有权
- `batchDelete()` - 在批量删除前检查 delete 权限和资源所有权
- `export()` - 在导出前检查 export 权限

### 权限检查流程

1. **基础权限检查**：首先检查用户是否有对应的操作权限（create、update、delete、export）
2. **所有者权限检查**：如果启用了 `enableOwnerFilter`，对于 update 和 delete 操作还会检查：
   - 用户是否是资源的所有者
   - 如果不是所有者，是否有 `update_all` 或 `delete_all` 权限

### 需要使用的替代方法

使用此 trait 后，需要使用以下方法替代原有方法：

| 原方法 | 新方法 | 说明 |
|--------|--------|------|
| `columns()` | `defineColumns()` | 定义列配置 |
| `query()` | `buildQuery()` | 构建基础查询 |
| `getActions()` | `defineActions()` | 定义操作按钮 |
| `getBatchActions()` | `defineBatchActions()` | 定义批量操作 |

### 权限元数据

DataTable 的 `meta()` 方法会自动包含权限信息：

```json
{
  "permission": {
    "enabled": true,
    "resource": "admin.users",
    "permissions": {
      "create": true,
      "read": true,
      "update": false,
      "delete": false,
      "export": true
    }
  }
}
```

## HasOwner Trait

为资源模型提供所有者管理功能：

### 基本用法

```php
use Dybasedev\LunaPrototype\Permission\Traits\HasOwner;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

class Document extends Model
{
    use HasOwner;
}

// 使用示例
$document = new Document();

// 设置所有者
$document->setOwner($sessionHolder);

// 检查所有权
if ($document->isOwnedBy($sessionHolder)) {
    // 是所有者
}

// 获取所有者信息
$ownerType = $document->getOwnerType();
$ownerId = $document->getOwnerId();

// 获取权限上下文
$context = $document->getResourcePermissionContext();
```

### 自定义字段名

```php
class CustomModel extends Model
{
    use HasOwner;
    
    // 自定义所有者字段名
    protected string $ownerTypeKeyName = 'creator_type';
    protected string $ownerIdKeyName = 'creator_id';
}
```

### 提供权限属性

```php
class Article extends Model
{
    use HasOwner;
    
    /**
     * 提供用于权限判断的额外属性
     */
    protected function getPermissionAttributes(): array
    {
        return [
            'status' => $this->status,
            'is_published' => $this->is_published,
            'category_id' => $this->category_id,
        ];
    }
}
```

## 辅助函数

### luna_showcase_check_permission

检查 DataTable 权限：

```php
// 检查是否有读取权限
if (luna_showcase_check_permission('users', 'read')) {
    // 有权限
}

// 检查是否有创建权限
if (luna_showcase_check_permission('posts', 'create')) {
    // 可以创建
}
```

### luna_showcase_can_operate

检查是否可以操作资源：

```php
$post = Post::find(1);

if (luna_showcase_can_operate($post, 'update', 'content.posts')) {
    // 可以更新
}
```

### luna_showcase_filter_columns

根据权限过滤列：

```php
$columns = [
    ['name' => 'id', 'title' => 'ID'],
    ['name' => 'email', 'title' => '邮箱'],
    ['name' => 'phone', 'title' => '电话'],
];

$columnPermissions = [
    'email' => 'view_email',
    'phone' => 'view_phone',
];

$filteredColumns = luna_showcase_filter_columns(
    $columns,
    'admin.users',
    $columnPermissions
);
```

## 工作原理

### 权限检查流程

1. **表级权限**：通过 `authorized()` 方法检查用户是否有访问 DataTable 的权限
2. **列级权限**：根据 `columnPermissions` 配置过滤可见列
3. **行级权限**：通过 `enableOwnerFilter` 过滤只显示用户拥有的记录
4. **操作权限**：根据权限过滤可用的操作按钮

### 所有者过滤机制

当启用 `enableOwnerFilter` 时：

1. 系统获取当前 SessionHolder
2. 在查询中添加所有者过滤条件
3. 只返回当前用户拥有的记录
4. 如果用户有 `view_all` 权限，则跳过过滤

### 权限资源命名

权限资源名称通过以下方式确定：

1. 优先使用 `$permissionResource` 属性
2. 其次使用 `resourceMappings` 中的映射
3. 最后使用 `resourcePattern` 生成

例如，如果 `resourcePattern` 为 `'admin.{key}'`，DataTable key 为 `'users'`，则资源名称为 `'admin.users'`。

## 与 Permission 组件的集成

### 权限检查

集成使用 Permission 组件的标准权限检查：

```php
luna_permission()->can($action, $resource, $context);
```

### SessionHolder

集成依赖 Foundation 组件的 SessionHolder 接口：

```php
interface SessionHolder
{
    public function getOperatorTypeName(): string;
    public function getOperatorType(): int;
    public function getOperatorId(): int;
    public function getSessionHolderContext(): ?array;
}
```

### 获取当前持有者

集成通过 Permission 组件的绑定获取当前用户：

```php
$holder = PermissionIntegration::getCurrentHolder();
```

## 最佳实践

### 1. 合理设置权限粒度

```php
protected function configurePermissions(): void
{
    // 基础资源权限
    $this->permissionResource = 'admin.users';
    
    // 敏感信息使用独立权限
    $this->columnPermissions = [
        'balance' => 'admin.users.financial',
        'id_number' => 'admin.users.sensitive',
    ];
}
```

### 2. 使用权限上下文

```php
public function canDeleteRecord($record): bool
{
    // 利用 HasOwner 提供的上下文
    if (method_exists($record, 'getResourcePermissionContext')) {
        $context = $record->getResourcePermissionContext();
        return luna_permission()->can('delete', $this->permissionResource, $context);
    }
    
    return false;
}
```

### 3. 处理权限不可用

```php
protected function defineColumns(Request $request): array
{
    $columns = [
        UI::column('ID', 'id'),
        UI::column('姓名', 'name'),
    ];
    
    // 手动检查权限（当不使用 columnPermissions 时）
    if (PermissionIntegration::isAvailable() && 
        luna_permission()->can('view_email', 'admin.users')) {
        $columns[] = UI::column('邮箱', 'email');
    }
    
    return $columns;
}
```

### 4. 缓存权限结果

```php
public function meta(Request $request): array
{
    $cacheKey = 'datatable.meta.' . $this->permissionResource . '.' . auth()->id();
    
    return Cache::remember($cacheKey, 300, function () use ($request) {
        return parent::meta($request);
    });
}
```

## 示例场景

### 场景一：多租户系统

```php
class TenantDataTable extends CrudDataTable
{
    use PermissionAwareDataTable;
    
    protected ?string $permissionResource = 'tenant.resources';
    protected bool $enableOwnerFilter = true;
    
    protected function buildQuery(Request $request): Builder
    {
        return $this->model()::query()
            ->where('tenant_id', auth()->user()->tenant_id);
    }
}
```

### 场景二：内容管理系统

```php
class ArticleDataTable extends CrudDataTable
{
    use PermissionAwareDataTable;
    
    protected ?string $permissionResource = 'content.articles';
    
    public function __construct()
    {
        // 作者只能看到自己的文章
        $this->enableOwnerFilter = !auth()->user()->hasRole('editor');
        
        // 编辑可以看到所有字段
        if (!auth()->user()->hasRole('editor')) {
            $this->columnPermissions = [
                'author_notes' => 'content.articles.notes',
                'internal_status' => 'content.articles.internal',
            ];
        }
    }
}
```

### 场景三：财务系统

```php
class TransactionDataTable extends CrudDataTable
{
    use PermissionAwareDataTable;
    
    protected ?string $permissionResource = 'finance.transactions';
    
    // 财务信息需要特殊权限
    protected array $columnPermissions = [
        'amount' => 'finance.view_amount',
        'account_number' => 'finance.view_account',
        'balance' => 'finance.view_balance',
    ];
    
    protected function defineActions(Request $request): array
    {
        $actions = [];
        
        // 根据权限显示不同操作
        if (luna_permission()->can('approve', 'finance.transactions')) {
            $actions[] = ['key' => 'approve', 'label' => '审批'];
        }
        
        if (luna_permission()->can('reject', 'finance.transactions')) {
            $actions[] = ['key' => 'reject', 'label' => '拒绝'];
        }
        
        return $actions;
    }
}
```

## 故障排除

### Permission 组件不可用

如果 Permission 组件未安装或配置，集成会自动降级：

- 所有权限检查返回 `true`
- 不应用任何过滤
- 所有列和操作都可见

### 所有者过滤不生效

检查以下几点：

1. 确认 `enableOwnerFilter` 已设置为 `true`
2. 确认模型有正确的所有者字段
3. 确认当前有有效的 SessionHolder

### 列权限不生效

1. 确认使用了 `defineColumns()` 而不是 `columns()`
2. 确认 `columnPermissions` 配置正确
3. 确认 Permission 组件可用

## 总结

Showcase Permission 集成提供了一个灵活、可选的权限控制方案，通过简单的配置即可为 DataTable 添加完整的权限功能。其设计遵循了 Luna Prototype 的核心理念：

- **原子化**：Permission 作为独立的可选组件
- **配置驱动**：通过 Builder 模式和配置类管理
- **向后兼容**：不影响现有 DataTable 的使用
- **灵活扩展**：支持自定义权限逻辑