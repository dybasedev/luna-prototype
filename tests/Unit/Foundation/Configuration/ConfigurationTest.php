<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfiguration;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure;
use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->configure = LunaConfigurationConfigure::create()->build();
    $this->configuration = new LunaConfiguration($this->configure, app('cache.store'));
});

it('可以创建配置组', function () {
    $group = $this->configuration->group('test-group');
    
    expect($group)->toBeInstanceOf(\Dybasedev\LunaPrototype\Foundation\Configuration\ConfigurationGroup::class);
});

it('可以创建并获取配置', function () {
    $group = $this->configuration->group('test-group');
    
    $repository = $group->create('test-config', 'Test Configuration', [
        'key1' => 'value1',
        'key2' => 'value2',
        'nested' => ['inner' => 'value']
    ]);
    
    expect($repository)->toBeInstanceOf(\Dybasedev\LunaPrototype\Foundation\Configuration\Repository::class);
    expect($repository->get('key1'))->toBe('value1');
    expect($repository->get('key2'))->toBe('value2');
    expect($repository->get('nested.inner'))->toBe('value');
});

it('可以检查配置是否存在', function () {
    $group = $this->configuration->group('test-group');
    
    $group->create('existing-config', 'Existing Configuration', ['test' => 'value']);
    
    expect($group->exists('existing-config'))->toBeTrue();
    expect($group->exists('non-existent'))->toBeFalse();
});

it('可以通过组获取配置', function () {
    $group = $this->configuration->group('test-group');
    
    $group->create('group-config', 'Group Configuration', ['setting' => 'value']);
    
    expect($group->get('group-config.setting'))->toBe('value');
    expect($group->get('group-config.non-existent', 'default'))->toBe('default');
});

it('可以通过组设置配置值', function () {
    $group = $this->configuration->group('test-group');
    
    $group->create('editable-config', 'Editable Configuration', ['original' => 'value']);
    
    $group->set('editable-config.new-key', 'new-value');
    
    expect($group->get('editable-config.new-key'))->toBe('new-value');
});

it('可以保存配置更改', function () {
    $group = $this->configuration->group('test-group');
    
    $group->create('save-config', 'Save Configuration', ['test' => 'original']);
    
    $group->set('save-config.test', 'modified');
    $group->save();
    
    // 清除缓存并重新获取
    app('cache.store')->flush();
    
    $newGroup = $this->configuration->group('test-group');
    expect($newGroup->get('save-config.test'))->toBe('modified');
});

it('将配置持久化到数据库', function () {
    $group = $this->configuration->group('test-group');
    
    $group->create('persistent-config', 'Persistent Configuration', ['key' => 'value']);
    
    // 验证数据库中存在配置
    $this->assertDatabaseHas('luna_configurations', [
        'name' => 'persistent-config',
        'group_id' => hash_code('test-group')
    ]);
    
    // 验证配置值表存在且包含正确的JSON数据
    $configValue = \Illuminate\Support\Facades\DB::table('luna_configuration_values')
        ->where('index_id', function($query) {
            $query->select('id')
                ->from('luna_configurations')
                ->where('name', 'persistent-config')
                ->where('group_id', hash_code('test-group'));
        })
        ->first();
    
    expect($configValue)->not->toBeNull();
    expect(json_decode($configValue->value, true))->toBe(['key' => 'value']);
});

it('优雅地处理配置不存在的情况', function () {
    $group = $this->configuration->group('test-group');
    
    try {
        $group->get('non-existent-config.key');
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (\Dybasedev\LunaPrototype\Foundation\Exception\LunaException $e) {
        expect(true)->toBeTrue(); // Correct exception type
    } catch (\Exception $e) {
        expect(false)->toBeTrue("Wrong exception type: " . get_class($e) . " - " . $e->getMessage());
    }
});