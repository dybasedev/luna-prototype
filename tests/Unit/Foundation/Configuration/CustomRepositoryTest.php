<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 自定义仓库类
class CustomAppRepository extends Repository
{
    public function getAppName(): string
    {
        return $this->get('name', 'Default App');
    }
    
    public function getVersion(): string
    {
        return $this->get('version', '0.0.0');
    }
    
    public function isDebugMode(): bool
    {
        return $this->get('debug', false);
    }
}

class CustomEmailRepository extends Repository
{
    public function getDriver(): string
    {
        return $this->get('driver', 'smtp');
    }
    
    public function getHost(): string
    {
        return $this->get('host', 'localhost');
    }
    
    public function getPort(): int
    {
        return $this->get('port', 587);
    }
}

test('可以绑定自定义仓库到特定配置', function () {
    // 创建配置并绑定自定义仓库
    $configure = app(LunaConfigurationConfigure::class);
    $configure->bindRepository('system', 'app', CustomAppRepository::class);
    $configure->bindRepository('system', 'email', CustomEmailRepository::class);
    
    // 创建配置管理器
    $config = new LunaConfiguration($configure, app('cache.store'));
    $systemGroup = $config->group('system');
    
    // 创建配置项
    $appRepo = $systemGroup->create('app', '应用配置', [
        'name' => 'Test App',
        'version' => '1.0.0',
        'debug' => true
    ]);
    
    $emailRepo = $systemGroup->create('email', '邮件配置', [
        'driver' => 'ses',
        'host' => 'smtp.example.com',
        'port' => 465
    ]);
    
    // 验证返回的是自定义仓库实例
    expect($appRepo)->toBeInstanceOf(CustomAppRepository::class);
    expect($emailRepo)->toBeInstanceOf(CustomEmailRepository::class);
    
    // 验证自定义方法可用
    expect($appRepo->getAppName())->toBe('Test App');
    expect($appRepo->getVersion())->toBe('1.0.0');
    expect($appRepo->isDebugMode())->toBeTrue();
    
    expect($emailRepo->getDriver())->toBe('ses');
    expect($emailRepo->getHost())->toBe('smtp.example.com');
    expect($emailRepo->getPort())->toBe(465);
});

test('未绑定的配置使用默认仓库', function () {
    $configure = app(LunaConfigurationConfigure::class);
    $configure->bindRepository('system', 'app', CustomAppRepository::class);
    
    $config = new LunaConfiguration($configure, app('cache.store'));
    $systemGroup = $config->group('system');
    
    // 创建未绑定自定义仓库的配置
    $defaultRepo = $systemGroup->create('other', '其他配置', [
        'key' => 'value'
    ]);
    
    // 验证返回的是默认仓库
    expect($defaultRepo)->toBeInstanceOf(Repository::class);
    expect($defaultRepo)->not->toBeInstanceOf(CustomAppRepository::class);
    expect($defaultRepo->get('key'))->toBe('value');
});

test('从缓存加载时也使用自定义仓库', function () {
    $configure = app(LunaConfigurationConfigure::class);
    $configure->bindRepository('cache_test', 'cached_app', CustomAppRepository::class);
    
    $config = new LunaConfiguration($configure, app('cache.store'));
    $cacheGroup = $config->group('cache_test');
    
    // 创建配置
    $appRepo = $cacheGroup->create('cached_app', '缓存应用配置', [
        'name' => 'Cached App',
        'version' => '2.0.0',
        'debug' => false
    ]);
    
    // 验证初始值
    expect($appRepo)->toBeInstanceOf(CustomAppRepository::class);
    expect($appRepo->getAppName())->toBe('Cached App');
    
    // 创建新的配置组实例，强制从缓存/数据库重新加载
    $newCacheGroup = $config->group('cache_test');
    $appRepoFromCache = $newCacheGroup->repository('cached_app');
    
    // 验证仍然是自定义仓库
    expect($appRepoFromCache)->toBeInstanceOf(CustomAppRepository::class);
    expect($appRepoFromCache->getAppName())->toBe('Cached App');
    expect($appRepoFromCache->getVersion())->toBe('2.0.0');
    expect($appRepoFromCache->isDebugMode())->toBeFalse();
});

test('获取版本时也使用自定义仓库', function () {
    $configure = app(LunaConfigurationConfigure::class);
    $configure->bindRepository('system', 'app', CustomAppRepository::class);
    
    $config = new LunaConfiguration($configure, app('cache.store'));
    $systemGroup = $config->group('system');
    
    // 创建配置
    $systemGroup->create('app', '应用配置', [
        'name' => 'Version Test App',
        'version' => '1.0.0',
        'debug' => true
    ]);
    
    // 修改配置创建新版本
    $systemGroup->set('app.version', '2.0.0');
    $systemGroup->save();
    
    // 获取版本列表
    $versions = $systemGroup->getVersionList('app');
    expect($versions)->toHaveCount(2);
    
    // 获取旧版本
    $oldVersionId = $versions[1]['version_id'];
    $oldVersion = $systemGroup->getVersion('app', $oldVersionId);
    
    // 验证版本也使用自定义仓库
    expect($oldVersion)->toBeInstanceOf(CustomAppRepository::class);
    expect($oldVersion->getVersion())->toBe('1.0.0');
});

test('不同组可以有不同的绑定', function () {
    $configure = app(LunaConfigurationConfigure::class);
    $configure->bindRepository('system', 'config', CustomAppRepository::class);
    $configure->bindRepository('email', 'config', CustomEmailRepository::class);
    
    $config = new LunaConfiguration($configure, app('cache.store'));
    
    // 在 system 组创建配置
    $systemGroup = $config->group('system');
    $systemConfig = $systemGroup->create('config', '系统配置', [
        'name' => 'System Config'
    ]);
    
    // 在 email 组创建同名配置
    $emailGroup = $config->group('email');
    $emailConfig = $emailGroup->create('config', '邮件配置', [
        'driver' => 'smtp'
    ]);
    
    // 验证使用了不同的自定义仓库
    expect($systemConfig)->toBeInstanceOf(CustomAppRepository::class);
    expect($emailConfig)->toBeInstanceOf(CustomEmailRepository::class);
});