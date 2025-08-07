<?php

use Dybasedev\LunaPrototype\Permission\LunaPermission;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\PermissionBinding;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Traits\HasPermissions;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\LunaSessionHolder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;

class TestPermissionMethodsUser extends Authenticatable implements PermissionSubject, SessionHolder
{
    use HasPermissions, LunaSessionHolder;
    
    protected $table = 'test_permission_methods_users';
    protected $fillable = ['name', 'email'];
    
    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }
}

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    
    // 创建用户表
    $this->app['db']->connection()->getSchemaBuilder()->create('test_permission_methods_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
    
    // 配置权限模块
    $this->configure = LunaPermissionConfigure::create()
        ->bind(new PermissionBinding(TestPermissionMethodsUser::class, 'test_user'));
    $this->app->instance(LunaPermissionConfigure::class, $this->configure);
    
    // 注册并启动权限模块以确保处理器被注册
    $this->configure->register($this->app);
    $this->configure->boot($this->app);
    
    // 创建测试用户
    $this->user = TestPermissionMethodsUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com'
    ]);
    
    // 创建测试策略
    $this->policy = Policy::create([
        'name' => 'test-policy',
        'description' => 'Test Policy',
        'current_version_id' => 'v1'
    ]);
    
    $this->policy->versions()->create([
        'version_id' => 'v1',
        'statement' => [
            'effect' => 'allow',
            'action' => ['read', 'update'],
            'resource' => 'posts.*'
        ]
    ]);
});

afterEach(function () {
    // 清理用户表
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('test_permission_methods_users');
});

it('luna_permission 函数返回 LunaPermission 实例', function () {
    $permission = luna_permission();
    
    expect($permission)->toBeInstanceOf(LunaPermission::class);
});

it('can 方法检查当前用户权限', function () {
    // 分配策略
    luna_permission()->assignPolicy($this->user, $this->policy);
    
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    expect(luna_permission()->can('read', 'posts.123'))->toBeTrue();
    expect(luna_permission()->can('delete', 'posts.123'))->toBeFalse();
});

it('cannot 方法检查相反权限', function () {
    // 分配策略
    luna_permission()->assignPolicy($this->user, $this->policy);
    
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    expect(luna_permission()->cannot('delete', 'posts.123'))->toBeTrue();
    expect(luna_permission()->cannot('read', 'posts.123'))->toBeFalse();
});

it('canAny 方法检查任一权限', function () {
    // 分配策略
    luna_permission()->assignPolicy($this->user, $this->policy);
    
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    expect(luna_permission()->canAny(['read', 'delete'], 'posts.123'))->toBeTrue();
    expect(luna_permission()->canAny(['delete', 'export'], 'posts.123'))->toBeFalse();
});

it('canAll 方法检查所有权限', function () {
    // 分配策略
    luna_permission()->assignPolicy($this->user, $this->policy);
    
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    expect(luna_permission()->canAll(['read', 'update'], 'posts.123'))->toBeTrue();
    expect(luna_permission()->canAll(['read', 'delete'], 'posts.123'))->toBeFalse();
});

it('authorize 方法抛出异常当无权限时', function () {
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    // 期望抛出 403 异常
    luna_permission()->authorize('delete', 'posts.123');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, '无权执行此操作');

it('authorize 方法支持模型实例', function () {
    // 创建一个模拟的 Post 模型
    $post = new class extends \Illuminate\Database\Eloquent\Model {
        protected $table = 'posts';
        protected $attributes = [
            'id' => 123,
            'title' => 'Test Post',
            'user_id' => 1
        ];
    };
    
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    // 期望抛出 403 异常
    luna_permission()->authorize('delete', $post);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, '无权执行此操作');

it('authorize 方法支持带上下文', function () {
    // 分配策略
    luna_permission()->assignPolicy($this->user, $this->policy);
    
    // 模拟登录
    Auth::shouldReceive('user')->andReturn($this->user);
    
    // 带上下文的授权检查
    luna_permission()->authorize('read', 'posts.123', [
        'ip' => '192.168.1.1'
    ]);
    
    expect(true)->toBeTrue(); // 如果没有异常，测试通过
});

it('处理未登录用户', function () {
    // 模拟未登录
    Auth::shouldReceive('user')->andReturn(null);
    
    expect(luna_permission()->can('read', 'posts'))->toBeFalse();
    expect(luna_permission()->cannot('read', 'posts'))->toBeTrue();
    expect(luna_permission()->canAny(['read', 'write'], 'posts'))->toBeFalse();
    expect(luna_permission()->canAll(['read', 'write'], 'posts'))->toBeFalse();
});

it('authorize 方法对未登录用户抛出异常', function () {
    // 模拟未登录
    Auth::shouldReceive('user')->andReturn(null);
    
    luna_permission()->authorize('read', 'posts');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, '用户未实现权限接口');