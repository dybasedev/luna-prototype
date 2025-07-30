<?php

namespace Tests\Foundation;

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
    
    public function test_luna_registered_modules_returns_module_configurations()
    {
        // 获取注册的模块
        $modules = luna_registered_modules();
        
        // 输出调试信息
        dump('Module count:', count($modules));
        dump('Module keys:', array_keys($modules));
        
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
            dump("Module: {$name}");
            dump("Module Class: " . get_class($module));
            dump("Module is instance of LunaModuleConfigure: " . ($module instanceof \Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure ? 'Yes' : 'No'));
            
            $this->assertInstanceOf(\Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure::class, $module);
        }
    }
}