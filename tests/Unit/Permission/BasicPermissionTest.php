<?php

use Dybasedev\LunaPrototype\Permission\LunaPermission;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Illuminate\Support\Facades\Cache;

it('权限组件基本功能测试', function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
    
    // 初始化权限模块
    $configure = LunaPermissionConfigure::create();
    $this->app->instance(LunaPermissionConfigure::class, $configure);
    
    // 注册并启动权限模块以确保处理器被注册
    $configure->register($this->app);
    $configure->boot($this->app);
    
    $permission = new LunaPermission($configure, Cache::store());
    
    // 1. 测试策略声明
    $statement = new PolicyStatement([
        'effect' => 'allow',
        'action' => 'posts:*',
        'resource' => 'posts',
    ]);
    expect($statement->matchAction('posts:read'))->toBeTrue();
    expect($statement->matchAction('posts:write'))->toBeTrue();
    
    // 2. 测试策略管理
    $policyName = 'test-policy-' . uniqid();
    $policy = $permission->createPolicy($policyName, [
        'effect' => 'allow',
        'action' => ['read', 'write'],
        'resource' => 'posts:*',
    ], 'Test Policy');
    
    expect($policy)->toBeInstanceOf(Policy::class);
    expect($policy->name)->toBe($policyName);
    
    // 3. 测试角色管理
    $roleName = 'editor-' . uniqid();
    $role = $permission->createRole($roleName, 'Editor', 'Can edit content');
    
    expect($role)->toBeInstanceOf(Role::class);
    expect($role->name)->toBe($roleName);
    expect($role->is_system)->toBeFalse();
    
    // 4. 测试策略分配
    $assignment = $permission->assignPolicy($role, $policy);
    expect($assignment->policy_id)->toBe((string)$policy->id);
    expect($assignment->subject_type)->toBe(hash_code('role'));
    expect($assignment->subject_id)->toBe((string)$role->id);
    
    // 5. 测试权限检查
    $canRead = $permission->check($role, 'read', 'posts:123');
    expect($canRead)->toBeTrue();
    
    $canDelete = $permission->check($role, 'delete', 'posts:123');
    expect($canDelete)->toBeFalse();
    
    // 6. 测试版本控制
    $policy->createVersion([
        'effect' => 'allow',
        'action' => ['read', 'write', 'delete'],
        'resource' => 'posts:*',
    ], 'Added delete permission');
    
    expect($policy->versions()->count())->toBe(2);
    
    // 7. 清理测试数据
    $permission->deletePolicy($policy);
    $permission->deleteRole($role);
    
    expect($permission->getPolicyByName($policyName))->toBeNull();
    expect($permission->getRoleByName($roleName))->toBeNull();
});