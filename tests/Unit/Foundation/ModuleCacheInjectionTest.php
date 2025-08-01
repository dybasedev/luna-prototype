<?php

namespace Tests\Unit\Foundation;

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
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
            // 不使用 LunaServiceProvider，因为它会注册所有模块导致依赖问题
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建模拟的 CacheManager 和 CacheRepository
        $cacheRepository = Mockery::mock(CacheRepository::class);
        $cacheManager = Mockery::mock(CacheManager::class);
        
        // 设置 CacheManager 的 driver() 方法返回 CacheRepository
        $cacheManager->shouldReceive('driver')->andReturn($cacheRepository);
        
        // 注册缓存相关的服务
        $this->app->instance('cache', $cacheManager);
        $this->app->singleton('cache.store', function () use ($cacheRepository) {
            return $cacheRepository;
        });
    }

    /**
     * 测试所有 Luna 模块的缓存注入是否正确
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('lunaModulesProvider')]
    public function test_luna_modules_use_correct_cache_injection($moduleClass, $serviceName): void
    {
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
        ];
    }

    /**
     * 测试直接使用 cache 会导致类型错误
     */
    public function test_using_cache_directly_throws_type_error(): void
    {
        $this->expectException(\TypeError::class);
        
        // 重置容器绑定，创建新的 mock
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        
        // 创建一个只返回 CacheManager 的绑定
        $cacheManager = Mockery::mock(CacheManager::class);
        $this->app->instance('cache', $cacheManager);
        
        // 为 cache.store 绑定提供 CacheRepository 以确保 Handler 可以创建
        $cacheRepository = Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $this->app->instance('cache.store', $cacheRepository);
        
        // 注册必要的依赖
        $handlerConfigure = new \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure();
        $handlerConfigure->register($this->app);
        
        // 尝试创建需要 CacheRepository 的服务
        $configure = new \Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure();
        
        // 直接使用 cache（CacheManager）而不是 cache.store（CacheRepository）应该抛出类型错误
        new \Dybasedev\LunaPrototype\Membership\LunaMembership(
            $configure,
            $cacheManager, // 直接传入 CacheManager 而不是 CacheRepository
            $this->app->make(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}