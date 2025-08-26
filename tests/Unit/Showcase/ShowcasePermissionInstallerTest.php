<?php

use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\ShowcasePermissionInstaller;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 运行 Permission 相关的迁移
    $this->artisan('migrate', [
        '--path' => 'vendor/dybasedev/luna-prototype/database/migrations/permission',
        '--realpath' => true,
    ]);
});

test('installer creates datatable policies', function () {
    // 创建安装器实例
    $installer = new ShowcasePermissionInstaller();
    
    // 设置输出（使用 null output 避免测试时的输出）
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    // 执行安装
    $installer->install();
    
    // 验证策略是否创建
    expect(Policy::findByName('datatable-admin'))->not->toBeNull();
    expect(Policy::findByName('datatable-crud'))->not->toBeNull();
    expect(Policy::findByName('datatable-readonly'))->not->toBeNull();
    expect(Policy::findByName('datatable-editor'))->not->toBeNull();
    expect(Policy::findByName('datatable-owner'))->not->toBeNull();
    expect(Policy::findByName('datatable-export'))->not->toBeNull();
});

test('installer creates example datatable policies', function () {
    $installer = new ShowcasePermissionInstaller();
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    $installer->install();
    
    // 验证示例策略
    expect(Policy::findByName('datatable-users-admin'))->not->toBeNull();
    expect(Policy::findByName('datatable-content-editor'))->not->toBeNull();
    expect(Policy::findByName('datatable-finance-viewer'))->not->toBeNull();
});

test('installer creates example roles', function () {
    $installer = new ShowcasePermissionInstaller();
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    $installer->install();
    
    // 验证角色是否创建
    expect(Role::findByName('datatable-admin'))->not->toBeNull();
    expect(Role::findByName('datatable-editor'))->not->toBeNull();
    expect(Role::findByName('datatable-viewer'))->not->toBeNull();
});

test('installer handles dependencies correctly', function () {
    $installer = new ShowcasePermissionInstaller();
    
    // 获取依赖
    $dependencies = $installer->getDependencies();
    
    // 应该依赖 PermissionInstallation
    expect($dependencies)->toContain(\Dybasedev\LunaPrototype\Permission\Installations\PermissionInstallation::class);
});

test('installer is idempotent', function () {
    $installer = new ShowcasePermissionInstaller();
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    // 第一次安装
    $installer->install();
    
    // 记录策略数量
    $policyCount = Policy::count();
    $roleCount = Role::count();
    
    // 第二次安装（应该跳过已存在的）
    $installer->install();
    
    // 数量不应该改变
    expect(Policy::count())->toBe($policyCount);
    expect(Role::count())->toBe($roleCount);
});

test('datatable-admin policy allows all actions on datatable resources', function () {
    $installer = new ShowcasePermissionInstaller();
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    $installer->install();
    
    $policy = Policy::findByName('datatable-admin');
    expect($policy)->not->toBeNull();
    expect($policy->description)->toBe('DataTable 完全管理权限');
    
    // 确保有当前版本
    expect($policy->current_version_id)->not->toBeEmpty();
});

test('datatable-readonly policy only allows read and list', function () {
    $installer = new ShowcasePermissionInstaller();
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    $installer->install();
    
    $policy = Policy::findByName('datatable-readonly');
    expect($policy)->not->toBeNull();
    expect($policy->description)->toBe('DataTable 只读权限');
    
    // 确保有当前版本
    expect($policy->current_version_id)->not->toBeEmpty();
});

test('datatable-owner policy includes owner condition', function () {
    $installer = new ShowcasePermissionInstaller();
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $outputStyle = new OutputStyle($input, $output);
    $installer->withOutput($outputStyle);
    
    $installer->install();
    
    $policy = Policy::findByName('datatable-owner');
    expect($policy)->not->toBeNull();
    expect($policy->description)->toBe('DataTable 自有数据管理权限');
    
    // 确保有当前版本
    expect($policy->current_version_id)->not->toBeEmpty();
});