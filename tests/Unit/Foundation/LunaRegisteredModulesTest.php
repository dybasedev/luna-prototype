<?php

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;

beforeEach(function () {
    // 注册测试服务提供者
    app()->register(TestLunaServiceProvider::class);
});

test('luna_registered_modules 函数返回模块配置', function () {
    // 获取注册的模块
    $modules = luna_registered_modules();
    
    // 检查是否返回数组
    expect($modules)->toBeArray();
    
    // 检查是否包含基础模块
    expect($modules)->toHaveKey('luna.config');
    expect($modules)->toHaveKey('luna.exception');
    expect($modules)->toHaveKey('luna.business-event');
    expect($modules)->toHaveKey('luna.handler');
    expect($modules)->toHaveKey('luna.app');
    
    // 检查自定义注册的模块
    expect($modules)->toHaveKey('luna.assets-account');
    expect($modules)->toHaveKey('luna.trade');
    
    // 检查每个模块的值
    foreach ($modules as $name => $module) {
        expect($module)->toBeInstanceOf(\Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure::class);
    }
});

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