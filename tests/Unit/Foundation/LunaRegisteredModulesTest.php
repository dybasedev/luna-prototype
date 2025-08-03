<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Foundation;

use Dybasedev\LunaPrototype\Tests\TestCase;
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;

class LunaRegisteredModulesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TestLunaServiceProvider::class,
        ];
    }
    
    /**
     *
     * 测试 luna_registered_modules 函数返回模块配置
     */
    public function test_luna_registered_modules_returns_module_configurations()
    {
        // 获取注册的模块
        $modules = luna_registered_modules();
        
        // 检查是否返回数组
        $this->assertIsArray($modules);
        
        // 检查是否包含基础模块
        $this->assertArrayHasKey('luna.config', $modules);
        $this->assertArrayHasKey('luna.exception', $modules);
        $this->assertArrayHasKey('luna.business-event', $modules);
        $this->assertArrayHasKey('luna.handler', $modules);
        $this->assertArrayHasKey('luna.app', $modules);
        
        // 检查自定义注册的模块
        $this->assertArrayHasKey('luna.assets-account', $modules);
        $this->assertArrayHasKey('luna.trade', $modules);
        
        // 检查每个模块的值
        foreach ($modules as $name => $module) {
            $this->assertInstanceOf(\Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure::class, $module);
        }
    }
}

// 测试专用的服务提供者
class TestLunaServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册一些测试模块
        $this->registerModule(LunaAssetsAccountConfigure::create()->build());
        $this->registerModule(LunaTradeConfigure::create()->build());
    }
}