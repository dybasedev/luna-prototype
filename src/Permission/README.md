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
        ->bind(new PermissionBinding(User::class))
        ->registerResource('users', SimpleResource::crud('users', '用户资源'))
        ->registerResource('posts', SimpleResource::crud('posts', '文章资源'))
        ->setSuperAdminChecker(function ($subject) {
            // 自定义超级管理员逻辑
            return $subject->getSubjectType() === 'user' && $subject->id === 1;
        })
        ->build()
);
```

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

### 3. 运行迁移和初始化

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
// 使用辅助函数（自动使用当前用户）
if (luna_permission_can('update', 'posts')) {
    // 有权限
}

// 检查多个权限
if (luna_permission_can_any(['create', 'update'], 'posts')) {
    // 有任一权限
}

if (luna_permission_can_all(['read', 'update'], 'posts')) {
    // 有所有权限
}

// 带上下文的检查
if (luna_permission_can('delete', 'posts', ['ip' => '192.168.1.100'])) {
    // 特定条件下的权限
}

// 通过模块接口检查指定用户
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

// 也可以使用辅助函数注册资源
luna_permission_register_resource('reports', SimpleResource::create('reports')
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

## 最佳实践

1. **最小权限原则**：只授予必要的权限
2. **使用资源通配符**：`api.*` 表示所有 API 资源
3. **拒绝优先**：Deny 规则总是优先于 Allow
4. **合理使用条件**：避免过于复杂的条件逻辑
5. **定期审查权限**：使用过期时间自动回收权限