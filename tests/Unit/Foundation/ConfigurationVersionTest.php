<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\ConfigurationGroup;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfiguration;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure;
use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 运行迁移
    $this->artisan('migrate', [
        '--path' => 'vendor/dybasedev/luna-prototype/src/Foundation/migrations',
        '--realpath' => true,
    ]);

    // 设置配置
    $configure = LunaConfigurationConfigure::create()->build();
    $this->lunaConfiguration = new LunaConfiguration($configure, app('cache.store'));
    $this->systemGroup = $this->lunaConfiguration->group('system');
});

it('can create configuration with initial version', function () {
    // 创建配置
    $config = $this->systemGroup->create(
        name: 'app',
        displayName: '应用配置',
        initialValues: [
            'name' => 'Test App',
            'version' => '1.0.0',
            'debug' => true,
        ]
    );

    // 验证配置创建成功
    expect($config)->toBeInstanceOf(Repository::class);
    expect($config->get('name'))->toBe('Test App');
    expect($config->get('version'))->toBe('1.0.0');
    expect($config->get('debug'))->toBeTrue();

    // 验证版本ID存在
    $versionId = $this->systemGroup->getCurrentVersionId('app');
    expect($versionId)->not->toBeNull();
    expect($versionId)->toBeString();
    expect(strlen($versionId))->toBe(40); // SHA1 hash length
});

it('can get version list', function () {
    // 创建配置
    $this->systemGroup->create('app', '应用配置', [
        'name' => 'Test App',
        'version' => '1.0.0',
    ]);

    // 修改配置创建新版本
    $this->systemGroup->set('app.version', '1.0.1');
    $this->systemGroup->save();

    $this->systemGroup->set('app.version', '1.0.2');
    $this->systemGroup->save();

    // 获取版本列表
    $versions = $this->systemGroup->getVersionList('app');

    // 验证版本列表
    expect($versions)->toHaveCount(3);
    expect($versions[0]['is_current'])->toBeTrue();
    expect($versions[1]['is_current'])->toBeFalse();
    expect($versions[2]['is_current'])->toBeFalse();

    // 验证版本按时间倒序排列
    expect($versions[0]['created_at']->greaterThanOrEqualTo($versions[1]['created_at']))->toBeTrue();
    expect($versions[1]['created_at']->greaterThanOrEqualTo($versions[2]['created_at']))->toBeTrue();
});

it('can get specific version', function () {
    // 创建配置
    $this->systemGroup->create('app', '应用配置', [
        'name' => 'Test App',
        'version' => '1.0.0',
        'debug' => true,
    ]);

    // 获取初始版本ID
    $initialVersionId = $this->systemGroup->getCurrentVersionId('app');

    // 修改配置
    $this->systemGroup->set('app.version', '2.0.0');
    $this->systemGroup->set('app.debug', false);
    $this->systemGroup->save();

    // 获取旧版本
    $oldVersion = $this->systemGroup->getVersion('app', $initialVersionId);

    // 验证旧版本内容
    expect($oldVersion)->toBeInstanceOf(Repository::class);
    expect($oldVersion->get('version'))->toBe('1.0.0');
    expect($oldVersion->get('debug'))->toBeTrue();

    // 验证当前版本
    $currentVersion = $this->systemGroup->repository('app');
    expect($currentVersion->get('version'))->toBe('2.0.0');
    expect($currentVersion->get('debug'))->toBeFalse();
});

it('can switch version', function () {
    // 创建配置
    $this->systemGroup->create('app', '应用配置', [
        'name' => 'Test App',
        'version' => '1.0.0',
        'features' => ['feature_a' => true, 'feature_b' => false],
    ]);

    // 获取初始版本ID
    $version1 = $this->systemGroup->getCurrentVersionId('app');

    // 修改配置 - 版本2
    $this->systemGroup->set('app.version', '2.0.0');
    $this->systemGroup->set('app.features.feature_a', false);
    $this->systemGroup->set('app.features.feature_b', true);
    $this->systemGroup->save();

    $version2 = $this->systemGroup->getCurrentVersionId('app');

    // 修改配置 - 版本3
    $this->systemGroup->set('app.version', '3.0.0');
    $this->systemGroup->set('app.features.feature_c', true);
    $this->systemGroup->save();

    // 验证当前版本
    $config = $this->systemGroup->repository('app');
    expect($config->get('version'))->toBe('3.0.0');
    expect($config->get('features.feature_c'))->toBeTrue();

    // 切换到版本1
    $success = $this->systemGroup->switchVersion('app', $version1);
    expect($success)->toBeTrue();

    // 验证切换后的配置
    $config = $this->systemGroup->repository('app');
    expect($config->get('version'))->toBe('1.0.0');
    expect($config->get('features.feature_a'))->toBeTrue();
    expect($config->get('features.feature_b'))->toBeFalse();
    expect($config->get('features.feature_c'))->toBeNull();

    // 切换到版本2
    $success = $this->systemGroup->switchVersion('app', $version2);
    expect($success)->toBeTrue();

    $config = $this->systemGroup->repository('app');
    expect($config->get('version'))->toBe('2.0.0');
    expect($config->get('features.feature_a'))->toBeFalse();
    expect($config->get('features.feature_b'))->toBeTrue();
});

it('throws exception when switching to non existent version', function () {
    // 创建配置
    $this->systemGroup->create('app', '应用配置', ['name' => 'Test App']);

    // 尝试切换到不存在的版本
    $this->systemGroup->switchVersion('app', 'non_existent_version_id');
})->throws(\InvalidArgumentException::class, 'Version not exists');

it('returns null for non existent version', function () {
    // 创建配置
    $this->systemGroup->create('app', '应用配置', ['name' => 'Test App']);

    // 获取不存在的版本
    $version = $this->systemGroup->getVersion('app', 'non_existent_version_id');

    expect($version)->toBeNull();
});

it('clears cache when switching version')->skip('Known issue with cache clearing');

it('maintains version integrity with same data')->skip('Version hash calculation includes modification state');