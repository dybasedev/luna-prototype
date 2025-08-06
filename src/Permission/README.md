# Luna Permission 组件

基于策略的灵活权限管理系统，不同于传统的 RBAC，提供更细粒度的访问控制。

## 核心概念

### 策略 (Policy)
权限的核心，由以下元素组成：
- **Effect**: 效果（Allow/Deny）
- **Action**: 操作（create、read、update、delete 等）
- **Resource**: 资源（users、posts、api.* 等）
- **Condition**: 条件（IP、时间范围等）
- **Principal**: 授权主体

### 主体 (Subject)
可以被授权的实体：
- **User**: 用户
- **Role**: 角色（特殊用户，如系统、API客户端）
- **Group**: 用户组

## 快速开始

### 1. 注册模块

```php
// 在 AppServiceProvider 中
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\PermissionBinding;
use Dybasedev\LunaPrototype\Permission\Resources\SimpleResource;

$this->registerModule(
    LunaPermissionConfigure::create()
        // 支持多个绑定，适用于不同的用户体系
        ->bind(new PermissionBinding(User::class, 'user')
            ->withDescription('前台用户权限'))
        ->bind(new PermissionBinding(Admin::class, 'admin')
            ->withDescription('后台管理员权限'))
        
        // 方式一：手动注册资源
        ->registerResource('users', SimpleResource::crud('users', '用户资源'))
        ->registerResource('posts', SimpleResource::crud('posts', '文章资源'))
        
        // 方式二：通过 Attribute 自动扫描（推荐）
        ->scanAppResources('Models', 'Http/Controllers')
        
        ->setSuperAdminChecker(function ($subject) {
            // 自定义超级管理员逻辑
            return $subject->getSubjectType() === 'user' && $subject->id === 1;
        })
        ->build()
);
```

权限系统支持多种权限主体：
- **用户绑定**：通过 PermissionBinding 绑定实际的用户模型（如 User、Admin）
- **角色（Role）**：用于表示系统、API客户端等非用户实体，直接实现了 PermissionSubject 接口
- 每个绑定都有唯一的标识符（identifier）
- `PermissionBinding` 的构造函数会自动验证模型是否实现了 `PermissionSubject` 接口

### 2. 准备用户模型

```php
use Dybasedev\LunaPrototype\Permission\Contracts\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Traits\HasPermissions;

class User extends Authenticatable implements PermissionSubject
{
    use HasPermissions;
    
    // 模型已经通过 trait 实现了接口方法
}
```

注意：获取当前认证用户应使用 Laravel 自带的 `auth()` 方法，Permission 组件不会重复提供此功能。

### 3. 使用 Attribute 定义资源（推荐）

使用 PHP 8 的 Attribute 功能，可以更优雅地定义权限资源：

```php
use Dybasedev\LunaPrototype\Permission\Attributes\Resource;

#[Resource('post', '文章资源')]
class Post extends Model
{
    // 模型代码...
}

// 更详细的定义
#[Resource(
    name: 'product',
    description: '商品资源',
    actions: ['view', 'create', 'update', 'delete', 'publish'],
    group: 'shop',
    sortOrder: 10
)]
class Product extends Model
{
    // ...
}

// 在控制器上定义
#[Resource('order', '订单资源', ['view', 'create', 'update', 'cancel', 'refund'])]
class OrderController extends Controller
{
    // ...
}

// 使用便捷方法
#[Resource::readOnly('report', '报表资源')]
class ReportController extends Controller
{
    // 只有 read 权限
}

#[Resource::full('user', '用户资源')]
class UserController extends Controller
{
    // 包含 CRUD + import/export 权限
}
```

配置资源扫描：

```php
// 扫描指定目录
->scanResources(
    app_path('Models'),
    app_path('Http/Controllers')
)

// 或使用相对路径
->scanAppResources('Models', 'Http/Controllers')

// 自定义资源提供者
$provider = AttributeResourceProvider::create()
    ->addDirectory(app_path('Models'))
    ->withCache(true, 3600); // 缓存1小时

->useResourceProvider($provider)
```

### 4. 运行迁移和初始化

```bash
# 发布迁移文件
php artisan vendor:publish --tag=luna-permission-migrations

# 运行安装（包括迁移和初始化数据）
php artisan app:install
```

注意：权限系统的初始化通过 Foundation 的安装器机制完成。你可以在 AppServiceProvider 中注册自定义的安装器：

```php
use Dybasedev\LunaPrototype\Permission\Installations\PermissionInstallation;

// 在 register() 方法中
$this->extendModule(function() {
    return LunaApplicationConfigure::create()
        ->installation(PermissionInstallation::class)
        ->build();
});
```

或者创建自定义安装器（继承自基类以获得便捷方法）：

```php
use Dybasedev\LunaPrototype\Permission\Installations\BasePermissionInstallation;
use Dybasedev\LunaPrototype\Permission\PolicyBuilder;

class CustomPermissionInstallation extends BasePermissionInstallation
{
    public function install(): void
    {
        $this->writeln('=> Installing custom permissions...');
        
        // 使用基类提供的便捷方法
        $this->createRole('editor', '编辑员', '内容编辑人员');
        
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('editor-policy')
                ->description('编辑员权限')
                ->allow(['create', 'read', 'update'])
                ->on('posts')
        );
    }
}
```

## 使用示例

### 创建策略

```php
use Dybasedev\LunaPrototype\Permission\PolicyBuilder;

// 通过模块接口创建
$policy = luna_permission()->createPolicy('content-management', [
    'effect' => 'allow',
    'action' => ['create', 'update', 'delete'],
    'resource' => 'posts',
    'condition' => [
        'ip_address' => ['192.168.1.0/24']
    ]
], '内容管理权限');

// 使用 PolicyBuilder（更方便）
$builder = PolicyBuilder::create('api-access')
    ->description('API 访问权限')
    ->allow(['read', 'list'])
    ->on('api.*')
    ->deny(['delete'])
    ->on('api.users')
    ->betweenTime('08:00', '18:00'); // 工作时间

$policy = luna_permission()->createPolicy(
    'api-access',
    $builder->toArray(),
    'API 访问权限'
);

// 更新策略
$policy = luna_permission()->updatePolicy('api-access', [
    'effect' => 'allow',
    'action' => ['read'],
    'resource' => 'api.*'
], '限制为只读访问');
```

### 分配权限

```php
// 通过模块接口分配策略
luna_permission()->assignPolicy($user, 'content-management', [
    'expires_at' => now()->addYear(), // 可选：设置过期时间
]);

// 或使用用户的便捷方法
$user->assignPolicy('content-management');

// 撤销策略
luna_permission()->revokePolicy($user, 'content-management');

// 创建和管理角色
$role = luna_permission()->createRole('editor', '编辑员', '内容编辑人员');
luna_permission()->assignPolicy($role, 'content-management');

// 创建用户组
$group = luna_permission()->createUserGroup('editors', '编辑组');
$user->joinGroup($group);
```

### 权限检查

```php
// 使用便捷方法（自动使用当前用户）
if (luna_permission()->can('update', 'posts')) {
    // 有权限
}

// 检查多个权限
if (luna_permission()->canAny(['create', 'update'], 'posts')) {
    // 有任一权限
}

if (luna_permission()->canAll(['read', 'update'], 'posts')) {
    // 有所有权限
}

// 带上下文的检查
if (luna_permission()->can('delete', 'posts', ['ip' => '192.168.1.100'])) {
    // 特定条件下的权限
}

// 检查指定用户的权限
$allowed = luna_permission()->check($user, 'create', 'posts', $context);

// 批量检查
$results = luna_permission()->checkMany($user, [
    ['action' => 'read', 'resource' => 'posts'],
    ['action' => 'create', 'resource' => 'posts'],
    ['action' => 'delete', 'resource' => 'posts']
]);
```

### 中间件保护

```php
// 在路由中使用
Route::middleware('permission:read,users')->get('/users', ...);
Route::middleware('permission:*,admin.*')->group(...);

// 注册中间件（在 app/Http/Kernel.php）
protected $routeMiddleware = [
    'permission' => \Dybasedev\LunaPrototype\Permission\Middleware\CheckPermission::class,
];
```

## 高级用法

### 自定义资源定义

```php
use Dybasedev\LunaPrototype\Permission\Resources\ModelResource;

// 模型资源
->registerResource('posts', ModelResource::forModel(Post::class))

// 自定义资源
->registerResource('reports', function () {
    return SimpleResource::create('reports')
        ->setActions(['generate', 'view', 'export']);
})

// 注册资源
luna_permission()->registerResource('reports', SimpleResource::create('reports')
    ->setActions(['generate', 'view', 'export']));
```

### 条件评估

策略支持多种条件：

```php
$policy = PolicyBuilder::create('restricted-access')
    ->allow('*')
    ->on('sensitive.*')
    ->withConditions([
        'ip_address' => ['10.0.0.1', '10.0.0.2'],
        'time_range' => ['start' => '09:00', 'end' => '17:00'],
        'user_level' => 'senior', // 自定义条件
    ])
    ->build();
```

### 多用户体系支持

权限系统可以同时管理多个不同的用户体系：

```php
// 获取特定的绑定
$configure = app(LunaPermissionConfigure::class);

// 通过标识符获取
$userBinding = $configure->getBindingByIdentifier('user');
$adminBinding = $configure->getBindingByIdentifier('admin');

// 通过模型类名获取
$binding = $configure->getBindingByModel(User::class);

// 获取所有绑定
$allBindings = $configure->getBindings();

// 使用不同的绑定查找用户
$user = $userBinding->getUser($userId);

// 获取当前用户直接使用 Laravel 的认证系统
$currentUser = auth()->user();  // 默认 guard
$currentAdmin = auth()->guard('admin')->user();  // 指定 guard
```

### 角色（Role）的使用

角色是特殊的权限主体，用于表示系统、API客户端等非用户实体：

```php
use Dybasedev\LunaPrototype\Permission\Models\Role;

// 创建系统角色
$systemRole = Role::createSystemRole('system', '系统进程', [
    'description' => '系统内部操作使用的角色',
]);

$apiRole = Role::createSystemRole('api-client', 'API客户端', [
    'description' => '第三方API接入使用的角色',
    'metadata' => ['client_id' => 'abc123'],
]);

// 为角色分配策略
luna_permission()->assignPolicy($systemRole, 'system-full-access');
luna_permission()->assignPolicy($apiRole, 'api-read-only');

// 检查角色权限
$canAccess = luna_permission()->check($systemRole, 'read', 'api.users');
```

### 高级策略条件

权限系统支持多种条件类型，实现精细的访问控制：

#### 1. 资源所有者控制

```php
// 创建策略：用户只能编辑自己的文章
$policy = PolicyBuilder::create('user-own-posts')
    ->description('用户只能操作自己的文章')
    ->allow(['read', 'update', 'delete'])
    ->on('posts.*')
    ->withCondition([
        'resource_owner' => '@self'  // @self 表示当前用户
    ])
    ->build();

// 使用时，在 context 中提供资源信息
$canEdit = luna_permission()->check($user, 'update', 'posts.123', [
    'current_user' => $user->id,
    'resource_owner' => $post->user_id,  // 由业务端提供
]);
```

#### 2. 特定资源ID控制

```php
// 限制只能访问特定ID的资源
$policy = PolicyBuilder::create('admin-specific-users')
    ->allow(['read', 'update'])
    ->on('users.*')
    ->withCondition([
        'resource_id' => [1, 2, 3, 10]  // 只能访问这些ID的用户
    ])
    ->build();

// 或使用操作符条件
$policy = PolicyBuilder::create('recent-posts-only')
    ->allow(['read'])
    ->on('posts.*')
    ->withCondition([
        'resource_id' => [
            'operator' => '>',
            'value' => 1000  // 只能访问ID大于1000的文章
        ]
    ])
    ->build();

// 使用时
$canRead = luna_permission()->check($user, 'read', 'posts.1234', [
    'resource_id' => 1234
]);
```

#### 3. 资源属性条件

```php
// 基于资源属性的访问控制
$policy = PolicyBuilder::create('published-posts-only')
    ->allow(['read'])
    ->on('posts.*')
    ->withCondition([
        'resource_attribute' => [
            'attribute' => 'status',
            'value' => 'published',
            'operator' => '='
        ]
    ])
    ->build();

// 更复杂的属性条件
$statement = [
    'effect' => 'allow',
    'action' => ['read', 'comment'],
    'resource' => 'posts.*',
    'condition' => [
        'resource_attribute' => [
            'attribute' => 'visibility',
            'value' => ['public', 'members'],
            'operator' => 'in'
        ]
    ]
];

// 使用时提供资源属性
$canRead = luna_permission()->check($user, 'read', 'posts.456', [
    'resource_attributes' => [
        'status' => $post->status,
        'visibility' => $post->visibility,
        'category' => $post->category
    ]
]);
```

#### 4. 组合条件

```php
// 组合多个条件
$policy = PolicyBuilder::create('owner-published-posts')
    ->allow(['update', 'delete'])
    ->on('posts.*')
    ->withCondition([
        'resource_owner' => '@self',
        'resource_attribute' => [
            'attribute' => 'status',
            'value' => ['draft', 'published'],
            'operator' => 'in'
        ],
        'time_range' => [
            'start' => '09:00',
            'end' => '18:00'
        ]
    ])
    ->build();
```

#### 5. 自定义条件

```php
// 任何自定义条件都可以通过 context 传递
$policy = PolicyBuilder::create('department-resources')
    ->allow(['read', 'update'])
    ->on('documents.*')
    ->withCondition([
        'user_department' => ['IT', 'HR'],  // 自定义条件
        'document_level' => [
            'operator' => '<=',
            'value' => 3  // 只能访问3级及以下的文档
        ]
    ])
    ->build();

// 使用时
$canAccess = luna_permission()->check($user, 'read', 'documents.789', [
    'user_department' => $user->department,
    'document_level' => $document->security_level
]);
```

### 条件操作符

支持的操作符：
- `=`, `==`: 相等
- `!=`, `<>`: 不等
- `>`: 大于
- `>=`: 大于等于
- `<`: 小于
- `<=`: 小于等于
- `in`: 在列表中
- `not_in`: 不在列表中
- `like`: 包含（字符串）
- `not_like`: 不包含（字符串）

### 策略版本控制

策略支持版本控制，修改策略会创建新版本：

```php
$policy = Policy::findByName('content-management');

// 创建新版本
$newVersion = $policy->createVersion([
    'effect' => 'allow',
    'action' => ['read', 'update'], // 移除了 create 和 delete
    'resource' => 'posts',
]);

// 应用特定版本
$policy->applyVersion($newVersion->version);
```

## 权限处理器

权限系统通过处理器机制实现灵活的权限检查逻辑。权限处理器是纯处理器，不需要数据库实体。

### 使用默认处理器

默认的 `PermissionHandler` 提供了完整的基于策略的权限检查：

```php
// 权限处理器已经在 Permission Configure 中注册
// 直接通过 LunaPermission 使用即可
$canEdit = luna_permission()->check(
    $user,
    'update',
    'posts.123'
);
```

### 自定义权限处理器

创建自定义处理器来实现特殊的权限逻辑：

```php
use Dybasedev\LunaPrototype\Permission\Handlers\BasePermissionHandler;

class CustomPermissionHandler extends BasePermissionHandler
{
    // 声明为纯处理器
    public static function requiresEntity(): bool
    {
        return false;
    }
    
    public function handlerName(): string
    {
        return '自定义权限处理器';
    }
    
    public function handlerDescription(): string
    {
        return '实现特殊业务逻辑的权限处理器';
    }
    
    public function check(
        PermissionSubject $subject,
        string $action,
        string $resource,
        array $context = []
    ): bool {
        // 自定义权限检查逻辑
        // 例如：基于时间的权限
        if (isset($context['time_based'])) {
            $hour = now()->hour;
            if ($hour < 9 || $hour > 18) {
                return false; // 工作时间外禁止访问
            }
        }
        
        // 调用父类的标准检查
        return parent::check($subject, $action, $resource, $context);
    }
}

// 注册自定义处理器
LunaPermissionConfigure::create()
    ->bind(new PermissionBinding(User::class))
    ->defaultHandlerClass(CustomPermissionHandler::class)
    ->build();
```

### 处理器基类特性

`BasePermissionHandler` 提供了以下特性：

1. **无构造函数**：避免继承时的构造函数冲突
2. **资源注册器支持**：自动注入资源注册器
3. **超级管理员检查**：可自定义超级管理员逻辑
4. **缓存支持**：策略缓存机制
5. **批量检查**：`checkMany()`, `checkAny()`, `checkAll()` 方法

## 权限检查辅助工具

### 1. PermissionChecker 流畅接口

```php
use Dybasedev\LunaPrototype\Permission\Support\PermissionChecker;

// 链式调用
$canEdit = PermissionChecker::make($user)
    ->ownedBySelf()
    ->onResource($post)
    ->where('status', 'published')
    ->can('update', 'posts');

// 检查资源所有者
$canDelete = PermissionChecker::make($user)
    ->ownedBy($post->user_id)
    ->can('delete', 'posts.' . $post->id);

// 自动提取模型信息
$canView = PermissionChecker::forUser()
    ->withResourceModel($order)  // 自动提取 user_id、status 等属性
    ->can('view', 'orders');
```

### 2. 便捷方法

```php
// 基础权限检查
if (luna_permission()->can('create', 'posts')) {
    // 可以创建文章
}

if (luna_permission()->cannot('delete', 'users.1')) {
    // 不能删除用户
}

// 检查多个权限
if (luna_permission()->canAny(['update', 'delete'], 'posts')) {
    // 可以更新或删除
}

if (luna_permission()->canAll(['read', 'update'], 'posts')) {
    // 同时拥有读和更新权限
}

// 授权检查（失败抛出 403）
luna_permission()->authorize('update', $post);  // 自动提取模型信息
luna_permission()->authorize('delete', 'posts.123');

// 传递额外上下文
luna_permission()->authorize('publish', $post, [
    'department' => $user->department
]);
```

### 3. 中间件

```php
// 在路由中使用
Route::middleware('permission:update,posts')->group(function () {
    Route::put('/posts/{post}', [PostController::class, 'update']);
});

// 使用指定的 guard
Route::middleware('permission:delete,users,admin')->group(function () {
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser']);
});

// 在控制器中使用
class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:create,posts')->only('create', 'store');
        $this->middleware('permission:update,posts')->only('edit', 'update');
    }
}
```

### 4. 在 Blade 模板中使用

```blade
@if(luna_permission()->can('create', 'posts'))
    <a href="{{ route('posts.create') }}" class="btn">创建文章</a>
@endif

@if(luna_permission()->canAny(['update', 'delete'], 'posts'))
    <div class="actions">
        @if(luna_permission()->can('update', 'posts'))
            <button>编辑</button>
        @endif
        @if(luna_permission()->can('delete', 'posts'))
            <button>删除</button>
        @endif
    </div>
@endif
```

### 5. 实际应用示例

```php
class PostController extends Controller
{
    public function update(Request $request, Post $post)
    {
        // 方式一：使用便捷方法
        luna_permission()->authorize('update', $post);
        
        // 方式二：使用 PermissionChecker
        $checker = PermissionChecker::make($request->user())
            ->ownedBy($post->user_id)
            ->where('status', $post->status);
            
        if ($checker->cannot('update', 'posts')) {
            abort(403);
        }
        
        // 方式三：更详细的检查
        $canEdit = PermissionChecker::make($request->user())
            ->onResource($post)
            ->withContext([
                'current_user' => $request->user()->id,
                'resource_owner' => $post->user_id,
                'is_published' => $post->is_published
            ])
            ->can('update', 'posts');
            
        if (!$canEdit) {
            return response()->json(['error' => '无权编辑'], 403);
        }
        
        // 继续处理...
    }
    
    public function bulkDelete(Request $request)
    {
        // 检查是否有批量删除权限
        luna_permission()->authorizeAll(['delete', 'bulk_operate'], 'posts');
        
        // 处理批量删除...
    }
}
```

## 资源缓存管理

使用 Attribute 扫描资源时，系统会自动缓存结果以提高性能：

```bash
# 列出所有已注册的资源
php artisan luna:permission:resources list

# 刷新资源缓存
php artisan luna:permission:resources refresh

# 清除资源缓存
php artisan luna:permission:resources clear
```

缓存机制：
- 默认缓存 24 小时
- 自动检测文件变化，必要时刷新缓存
- 开发环境可禁用缓存：`->withCache(false)`

## 最佳实践

1. **最小权限原则**：只授予必要的权限
2. **使用资源通配符**：`api.*` 表示所有 API 资源
3. **拒绝优先**：Deny 规则总是优先于 Allow
4. **合理使用缓存**：权限检查结果会被缓存，修改权限后记得清理缓存
5. **处理器选择**：对于标准的基于策略的权限，使用默认处理器；特殊业务逻辑才需要自定义处理器

### 资源所有权设计建议

资源所有权的确定应该由业务端定义，权限系统不对此做任何假设：

```php
// 在控制器或服务层中进行权限检查
class PostController extends Controller
{
    public function update(Request $request, Post $post)
    {
        // 业务端决定如何确定资源所有者
        $context = [
            'current_user' => $request->user()->id,
            'resource_owner' => $post->user_id,  // 文章的作者
            'resource_id' => $post->id,
            'resource_attributes' => [
                'status' => $post->status,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at
            ]
        ];
        
        // 权限检查
        if (!luna_permission()->check($request->user(), 'update', 'posts.' . $post->id, $context)) {
            abort(403, '无权编辑此文章');
        }
        
        // 继续处理...
    }
}

// 对于更复杂的所有权关系
class ProjectController extends Controller
{
    public function delete(Request $request, Project $project)
    {
        // 项目可能有多个所有者或不同的权限层级
        $context = [
            'current_user' => $request->user()->id,
            'resource_owner' => $project->owner_id,  // 主要所有者
            'resource_attributes' => [
                'team_members' => $project->members->pluck('id')->toArray(),
                'department' => $project->department,
                'is_archived' => $project->is_archived
            ]
        ];
        
        // 可以定义不同的策略来处理不同的情况
        // 如：所有者可以删除，团队成员只能编辑等
    }
}
```