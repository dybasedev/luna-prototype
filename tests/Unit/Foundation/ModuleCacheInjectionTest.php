<?php

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;

beforeEach(function () {
    // 创建模拟的 CacheManager 和 CacheRepository
    $cacheRepository = \Mockery::mock(CacheRepository::class);
    $cacheManager = \Mockery::mock(CacheManager::class);
    
    // 设置 CacheManager 的 driver() 方法返回 CacheRepository
    $cacheManager->shouldReceive('driver')->andReturn($cacheRepository);
    
    // 注册缓存相关的服务
    app()->instance('cache', $cacheManager);
    app()->singleton('cache.store', function () use ($cacheRepository) {
        return $cacheRepository;
    });
});

afterEach(function () {
    Mockery::close();
});

test('Luna 模块使用正确的缓存注入', function ($moduleClass, $serviceName) {
    // 执行模块的注册方法
    $configure = new $moduleClass();
    $configure->register(app());
    
    // 解析服务，验证是否能正确创建
    $service = app()->make($serviceName);
    
    // 断言服务已成功创建
    expect($service)->not->toBeNull();
})->with([
    'Foundation Handler' => [
        'Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure',
        'luna.handler'
    ],
    'Foundation Configuration' => [
        'Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure',
        'luna.config'
    ],
    'Foundation BusinessEvent' => [
        'Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure',
        'luna.business-event'
    ],
    'AssetsAccount' => [
        'Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure',
        'luna.assets-account'
    ],
    'Membership' => [
        'Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure',
        'luna.membership'
    ],
    'Trade' => [
        'Dybasedev\LunaPrototype\Trade\LunaTradeConfigure',
        'luna.trade'
    ],
    'Schedule' => [
        'Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure',
        'luna.schedule'
    ],
    'HoldingObject' => [
        'Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObjectConfigure',
        'luna.holding-object'
    ],
    'UnitConversion' => [
        'Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionConfigure',
        'luna.unit-conversion'
    ],
    'Permission' => [
        'Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure',
        'luna.permission'
    ],
]);

test('直接使用 cache 会导致类型错误', function () {
    // 重置容器绑定，创建新的 mock
    app()->forgetInstance('cache');
    app()->forgetInstance('cache.store');
    
    // 创建一个只返回 CacheManager 的绑定
    $cacheManager = Mockery::mock(CacheManager::class);
    app()->instance('cache', $cacheManager);
    
    // 为 cache.store 绑定提供 CacheRepository 以确保 Handler 可以创建
    $cacheRepository = Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
    app()->instance('cache.store', $cacheRepository);
    
    // 注册必要的依赖
    $handlerConfigure = new \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure();
    $handlerConfigure->register(app());
    
    // 尝试创建需要 CacheRepository 的服务
    $configure = new \Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure();
    
    // 直接使用 cache（CacheManager）而不是 cache.store（CacheRepository）应该抛出类型错误
    expect(function () use ($configure, $cacheManager) {
        new \Dybasedev\LunaPrototype\Membership\LunaMembership(
            $configure,
            $cacheManager, // 直接传入 CacheManager 而不是 CacheRepository
            app()->make(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
        );
    })->toThrow(\TypeError::class);
});