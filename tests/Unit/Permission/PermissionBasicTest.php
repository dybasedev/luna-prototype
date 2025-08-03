<?php

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Permission\LunaPermission;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
    
    // 配置权限模块
    $this->configure = LunaPermissionConfigure::create();
    $this->app->instance(LunaPermissionConfigure::class, $this->configure);
    
    // 创建权限实例
    $this->permission = new LunaPermission($this->configure, Cache::store());
});

it('策略声明验证', function () {
    // 有效的声明
    $statement = new PolicyStatement([
        'effect' => 'allow',
        'action' => 'read',
        'resource' => 'posts',
    ]);
    
    expect($statement->validate())->toBeTrue();
    
    // 无效的效果
    $statement = new PolicyStatement([
        'effect' => 'invalid',
        'action' => 'read',
        'resource' => 'posts',
    ]);
    
    expect(fn() => $statement->validate())->toThrow(LunaException::class);
});

it('策略声明通配符匹配', function () {
    $statement = new PolicyStatement(['action' => 'posts:*']);
    
    expect($statement->matchAction('posts:read'))->toBeTrue();
    expect($statement->matchAction('posts:write'))->toBeTrue();
    expect($statement->matchAction('comments:read'))->toBeFalse();
});

it('创建和管理策略', function () {
    // 创建策略
    $policy = $this->permission->createPolicy('test-policy-basic', [
        'effect' => 'allow',
        'action' => ['read', 'write'],
        'resource' => 'posts:*',
    ], 'Test Policy');
    
    expect($policy)->toBeInstanceOf(Policy::class);
    expect($policy->name)->toBe('test-policy-basic');
    expect($policy->current_version_id)->not->toBeNull();
    
    // 获取策略
    $found = $this->permission->getPolicyByName('test-policy-basic');
    expect($found->id)->toBe((string)$policy->id);
    
    // 保存原始版本ID
    $originalVersionId = $policy->current_version_id;
    
    // 更新策略
    $updated = $this->permission->updatePolicy($policy, [
        'effect' => 'allow',
        'action' => ['read', 'write', 'delete'],
        'resource' => 'posts:*',
    ], 'Added delete permission');
    
    expect($updated->current_version_id)->not->toBe($originalVersionId);
    
    // 删除策略
    $result = $this->permission->deletePolicy($policy);
    expect($result)->toBeTrue();
    expect($this->permission->getPolicyByName('test-policy-basic'))->toBeNull();
});

it('创建和管理角色', function () {
    // 创建角色
    $role = $this->permission->createRole('editor-basic', 'Content Editor', 'Can edit content');
    
    expect($role)->toBeInstanceOf(Role::class);
    expect($role->name)->toBe('editor-basic');
    expect($role->display_name)->toBe('Content Editor');
    expect($role->is_system)->toBeFalse();
    
    // 创建系统角色
    $systemRole = Role::createSystemRole('system-admin', 'System Admin');
    expect($systemRole->is_system)->toBeTrue();
    
    // 尝试删除系统角色
    expect(fn() => $this->permission->deleteRole($systemRole))
        ->toThrow(LunaException::class, '系统角色不允许删除');
});

it('策略分配和权限检查', function () {
    // 创建策略
    $policy = $this->permission->createPolicy('read-posts', [
        'effect' => 'allow',
        'action' => ['read', 'list'],
        'resource' => 'posts:*',
    ]);
    
    // 创建角色
    $role = $this->permission->createRole('reader', 'Reader');
    
    // 分配策略
    $assignment = $this->permission->assignPolicy($role, $policy);
    expect($assignment->policy_id)->toBe((string)$policy->id);
    expect($assignment->subject_type)->toBe(hash_code('role'));
    expect($assignment->subject_id)->toBe((string)$role->id);
    
    // 权限检查
    $canRead = $this->permission->check($role, 'read', 'posts:123');
    expect($canRead)->toBeTrue();
    
    $canWrite = $this->permission->check($role, 'write', 'posts:123');
    expect($canWrite)->toBeFalse();
    
    // 撤销策略
    $result = $this->permission->revokePolicy($role, $policy);
    expect($result)->toBe(1);
});

it('策略版本控制', function () {
    $policy = Policy::create(['name' => 'versioned-policy']);
    
    // 创建第一个版本
    $policy->createVersion([
        'effect' => 'allow',
        'action' => 'read',
        'resource' => 'posts',
    ], 'Version 1');
    
    $version1 = $policy->current_version_id;
    
    // 创建第二个版本
    $policy->createVersion([
        'effect' => 'allow',
        'action' => ['read', 'write'],
        'resource' => 'posts',
    ], 'Version 2');
    
    $version2 = $policy->current_version_id;
    
    expect($version2)->not->toBe($version1);
    
    // 切换回第一个版本
    $result = $policy->applyVersion($version1);
    expect($result)->toBeTrue();
    
    $policy->refresh();
    expect($policy->current_version_id)->toBe($version1);
});

it('策略声明构建器', function () {
    $statement = PolicyStatement::builder()
        ->allow()
        ->action(['read', 'write'])
        ->resource('posts:*')
        ->condition('ip', '192.168.1.0/24')
        ->principal('role:editor')
        ->build();
    
    expect($statement)->toBe([
        'effect' => 'allow',
        'action' => ['read', 'write'],
        'resource' => 'posts:*',
        'condition' => ['ip' => '192.168.1.0/24'],
        'principal' => 'role:editor',
    ]);
});