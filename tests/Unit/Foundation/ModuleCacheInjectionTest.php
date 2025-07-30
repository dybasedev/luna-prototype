<?php

namespace Tests\Unit\Foundation;

use Dybasedev\LunaPrototype\Foundation\LunaFoundationServiceProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Mockery;
use Orchestra\Testbench\TestCase;

/**
 * 测试 Luna 模块的缓存注入模式
 * 
 * 确保所有模块正确使用 cache.store 而不是 cache
 */
class ModuleCacheInjectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LunaFoundationServiceProvider::class,
        ];
    }

    /**
     * 测试所有 Luna 模块的缓存注入是否正确
     * 
     * @dataProvider lunaModulesProvider
     */
    public function test_luna_modules_use_correct_cache_injection($moduleClass, $serviceName): void
    {
        // 创建模拟的 CacheManager
        $cacheManager = Mockery::mock(CacheManager::class);
        $cacheRepository = Mockery::mock(CacheRepository::class);
        
        // 设置期望：cache.store 应该返回 Repository 实例
        $cacheManager->shouldReceive('store')->andReturn($cacheRepository);
        
        // 绑定到容器
        $this->app->instance('cache', $cacheManager);
        $this->app->bind('cache.store', function () use ($cacheRepository) {
            return $cacheRepository;
        });

        // 执行模块的注册方法
        $configure = new $moduleClass();
        $configure->register($this->app);
        
        // 解析服务，验证是否能正确创建
        $service = $this->app->make($serviceName);
        
        // 断言服务已成功创建
        $this->assertNotNull($service);
    }

    /**
     * 提供所有需要测试的 Luna 模块
     */
    public static function lunaModulesProvider(): array
    {
        return [
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
                'luna.event'
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
        ];
    }

    /**
     * 测试直接使用 cache 会导致类型错误
     */
    public function test_using_cache_directly_throws_type_error(): void
    {
        $this->expectException(\TypeError::class);
        
        // 模拟错误的缓存注入
        $cacheManager = Mockery::mock(CacheManager::class);
        $this->app->instance('cache', $cacheManager);
        
        // 尝试创建需要 CacheRepository 的服务
        $configure = new \Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure();
        
        // 直接使用 cache 而不是 cache.store 应该抛出类型错误
        new \Dybasedev\LunaPrototype\Membership\LunaMembership(
            $configure,
            $this->app->make('cache'), // 错误：应该使用 cache.store
            $this->app->make(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}