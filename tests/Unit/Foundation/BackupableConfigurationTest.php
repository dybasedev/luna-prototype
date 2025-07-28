<?php

use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitCategory;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitConversionRule;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\UserGroup;

test('配置类模型都实现了 Backupable 接口', function () {
    // 检查所有模型是否实现了 Backupable 接口
    $models = [
        Handler::class,
        Configuration::class,
        BusinessEvent::class,
        AssetsAccountType::class,
        MembershipMilestoneType::class,
        ScheduleTask::class,
        UnitCategory::class,
        UnitDefinition::class,
        UnitConversionRule::class,
        Role::class,
        Policy::class,
        UserGroup::class,
    ];
    
    foreach ($models as $model) {
        expect($model)->toImplement(\Dybasedev\LunaPrototype\Foundation\Backupable::class);
    }
});

test('可以获取备份关联键', function () {
    // 使用 NamedId 的模型应该返回 'name'
    expect(Handler::getBackupableRelationKey())->toBe('name');
    expect(Configuration::getBackupableRelationKey())->toBe('name');
    expect(BusinessEvent::getBackupableRelationKey())->toBe('name');
    expect(AssetsAccountType::getBackupableRelationKey())->toBe('name');
    expect(MembershipMilestoneType::getBackupableRelationKey())->toBe('name');
    expect(ScheduleTask::getBackupableRelationKey())->toBe('name');
    expect(UnitCategory::getBackupableRelationKey())->toBe('name');
    expect(Role::getBackupableRelationKey())->toBe('name');
    expect(Policy::getBackupableRelationKey())->toBe('name');
    expect(UserGroup::getBackupableRelationKey())->toBe('name');
    
    // 使用复合键的模型
    expect(UnitDefinition::getBackupableRelationKey())->toBe(['category_id', 'code']);
    expect(UnitConversionRule::getBackupableRelationKey())->toBe(['from_unit_id', 'to_unit_id', 'handler_id']);
});

test('可以获取备份依赖关系', function () {
    // 检查依赖关系
    expect(Handler::getBackupableDependencies())->toBe([]);
    expect(Configuration::getBackupableDependencies())->toBe([]);
    
    expect(BusinessEvent::getBackupableDependencies())->toContain(Handler::class);
    expect(AssetsAccountType::getBackupableDependencies())->toContain(Handler::class);
    expect(MembershipMilestoneType::getBackupableDependencies())->toContain(Handler::class);
    
    expect(UnitDefinition::getBackupableDependencies())->toContain(UnitCategory::class);
    expect(UnitConversionRule::getBackupableDependencies())->toContain(UnitDefinition::class);
    expect(UnitConversionRule::getBackupableDependencies())->toContain(Handler::class);
});

test('备份和恢复基本功能', function () {
    // 加载必要的迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Handler/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Configuration/migrations');
    
    // 创建测试数据
    $handler = Handler::create([
        'name' => 'test-handler',
        'group_id' => hash_code('test-group'),
        'display_name' => 'Test Handler',
        'description' => 'Test handler for backup',
        'handler' => 'App\Handlers\TestHandler',
        'config' => ['key' => 'value'],
        'enabled' => true,
    ]);
    
    $config = Configuration::create([
        'name' => 'test-config',
        'group_id' => hash_code('settings'),
        'display_name' => 'Test Configuration',
        'description' => 'Test configuration for backup',
    ]);
    
    // 为配置创建版本（需要使用正确的方法名）
    $config->createVersionValue(['value' => ['setting1' => 'value1', 'setting2' => 'value2']]);
    
    // 获取备份数据
    $handlerBackup = Handler::backupDatasourceIterator();
    $configBackup = Configuration::backupDatasourceIterator();
    
    // 验证备份数据
    $handlerData = iterator_to_array($handlerBackup);
    expect($handlerData)->toHaveCount(1);
    expect($handlerData[0]['name'])->toBe('test-handler');
    
    $configData = iterator_to_array($configBackup);
    expect($configData)->toHaveCount(1);
    expect($configData[0]['name'])->toBe('test-config');
    expect($configData[0]['_versions'])->toHaveCount(1);
    
    // 删除原始数据
    $handler->delete();
    $config->versions()->delete();
    $config->delete();
    
    // 验证数据已删除
    expect(Handler::where('name', 'test-handler')->exists())->toBeFalse();
    expect(Configuration::where('name', 'test-config')->exists())->toBeFalse();
    
    // 恢复数据
    Handler::recoverFromBackupIterator(new ArrayIterator($handlerData));
    Configuration::recoverFromBackupIterator(new ArrayIterator($configData));
    
    // 验证数据已恢复
    $restoredHandler = Handler::where('name', 'test-handler')->first();
    expect($restoredHandler)->not->toBeNull();
    expect($restoredHandler->display_name)->toBe('Test Handler');
    expect($restoredHandler->config)->toBe(['key' => 'value']);
    
    $restoredConfig = Configuration::where('name', 'test-config')->first();
    expect($restoredConfig)->not->toBeNull();
    expect($restoredConfig->display_name)->toBe('Test Configuration');
    expect($restoredConfig->versions)->toHaveCount(1);
    expect($restoredConfig->current?->value)->toBe(['setting1' => 'value1', 'setting2' => 'value2']);
});

test('模型可以声明备份名称', function () {
    // 验证备份名称
    expect(Handler::getBackupableName())->toBe('luna_handlers');
    expect(Configuration::getBackupableName())->toBe('luna_configurations');
    expect(BusinessEvent::getBackupableName())->toBe('luna_business_events');
    expect(AssetsAccountType::getBackupableName())->toBe('luna_assets_account_types');
    expect(MembershipMilestoneType::getBackupableName())->toBe('luna_membership_milestone_types');
    expect(ScheduleTask::getBackupableName())->toBe('luna_schedule_tasks');
    expect(UnitCategory::getBackupableName())->toBe('luna_unit_categories');
    expect(UnitDefinition::getBackupableName())->toBe('luna_unit_definitions');
    expect(UnitConversionRule::getBackupableName())->toBe('luna_unit_conversion_rules');
    expect(Role::getBackupableName())->toBe('luna_permission_roles');
    expect(Policy::getBackupableName())->toBe('luna_permission_policies');
    expect(UserGroup::getBackupableName())->toBe('luna_permission_user_groups');
});