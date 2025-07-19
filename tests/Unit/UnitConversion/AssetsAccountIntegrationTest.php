<?php

use Dybasedev\LunaPrototype\AssetsAccount\AccountHandler;
use Dybasedev\LunaPrototype\AssetsAccount\AccountTypeCreationRequest;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccountIntegration;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionConfigure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 设置Handler迁移路径
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Handler/migrations');
    
    // 注册Handler组
    $handlerConfigure = \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure::create()
        ->group('assets-accounts', '资产账户', function ($register) {
            $register->handler(TestCurrencyAccountHandler::class);
        })
        ->build();
        
    app()->instance(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class, 
        new \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler(
            $handlerConfigure,
            app('cache.store')
        )
    );
});

// 测试用账户处理器
class TestCurrencyAccountHandler extends AccountHandler
{
    public function handlerName(): string
    {
        return '多币种账户';
    }
    
    public function handlerDescription(): string
    {
        return '支持多币种的账户处理器';
    }
}

// 测试用户
class TestAccountUser implements SessionHolder
{
    public function __construct(public int $id = 1) {}
    
    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('test_user');
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return null;
    }
}


beforeEach(function () {
    // 设置单位转换组件
    $unitConfigure = LunaUnitConversionConfigure::create()->build();
    $this->unitConversion = new LunaUnitConversion(
        $unitConfigure,
        app('cache.store'),
        app(LunaHandler::class)
    );
    
    // 注册到容器以便辅助函数可以访问
    app()->instance(LunaUnitConversion::class, $this->unitConversion);
    
    // 初始化预定义数据
    $this->unitConversion->initializePredefinedData();
    
    // 设置资产账户组件
    $assetsConfigure = LunaAssetsAccountConfigure::create()->build();
    $this->assetsAccount = new LunaAssetsAccount(
        $assetsConfigure,
        app(LunaHandler::class),
        app('cache.store')
    );
});

test('检查单位转换组件是否可用', function () {
    expect(AssetsAccountIntegration::isAvailable())->toBeTrue();
    
    // 测试组件不可用的情况 - 需要在另一个进程中测试
    // 由于单例模式，这里无法完全模拟组件不存在的情况
});

test('为账户类型添加货币支持', function () {
    // 获取USD货币信息
    $currency = $this->unitConversion->getUnit('USD', 'currency');
    
    expect($currency)->not->toBeNull();
    expect($currency->code)->toBe('USD');
    expect($currency->symbol)->toBe('$');
    expect($currency->precision)->toBe(2);
    
    // 测试构建货币元数据
    $metadata = [
        'currency' => [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'display_name' => $currency->display_name,
            'precision' => $currency->precision,
        ]
    ];
    
    expect($metadata)->toHaveKey('currency');
    expect($metadata['currency']['code'])->toBe('USD');
});

test('创建多币种子账户配置', function () {
    // 测试多币种配置的生成
    $currencies = ['USD', 'CNY', 'EUR'];
    $configs = [];
    
    foreach ($currencies as $currencyCode) {
        $currency = $this->unitConversion->getUnit($currencyCode, 'currency');
        if ($currency) {
            $configs[$currencyCode] = [
                'name' => 'wallet_' . strtolower($currencyCode),
                'display_name' => '钱包 - ' . $currency->display_name,
                'metadata' => [
                    'currency' => [
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                        'display_name' => $currency->display_name,
                        'precision' => $currency->precision,
                    ]
                ]
            ];
        }
    }
    
    expect($configs)->toHaveCount(3);
    expect($configs)->toHaveKeys(['USD', 'CNY', 'EUR']);
    
    // 检查配置
    expect($configs['USD']['name'])->toBe('wallet_usd');
    expect($configs['USD']['display_name'])->toBe('钱包 - 美元');
    expect($configs['USD']['metadata']['currency']['code'])->toBe('USD');
    
    expect($configs['CNY']['name'])->toBe('wallet_cny');
    expect($configs['CNY']['display_name'])->toBe('钱包 - 人民币');
    expect($configs['CNY']['metadata']['currency']['code'])->toBe('CNY');
});

test('转换账户余额到指定货币', function () {
    // USD到CNY的转换
    $convertedAmount = AssetsAccountIntegration::convertBalance(100, 'USD', 'CNY');
    expect($convertedAmount)->toBe(700.0); // 基于预设的汇率
    
    // EUR到USD的转换
    $convertedAmount2 = AssetsAccountIntegration::convertBalance(85, 'EUR', 'USD');
    expect($convertedAmount2)->toBe(100.0);
});

test('获取账户的货币信息', function () {
    $metadata = [
        'currency' => [
            'code' => 'USD',
            'symbol' => '$',
            'display_name' => '美元',
            'precision' => 2,
        ],
        'other_info' => 'test',
    ];
    
    $currency = AssetsAccountIntegration::getAccountCurrency($metadata);
    expect($currency)->not->toBeNull();
    expect($currency['code'])->toBe('USD');
    expect($currency['symbol'])->toBe('$');
    
    // 没有货币信息的情况
    $noCurrency = AssetsAccountIntegration::getAccountCurrency(['other_info' => 'test']);
    expect($noCurrency)->toBeNull();
});

test('格式化账户余额', function () {
    $metadata = [
        'currency' => [
            'code' => 'CNY',
            'symbol' => '¥',
            'precision' => 2,
        ],
    ];
    
    // 使用单位转换组件格式化
    $formatted = AssetsAccountIntegration::formatAccountBalance(1234.567, $metadata);
    expect($formatted)->toBe('¥1,234.57');
    
    // 降级处理（没有单位转换组件）
    app()->forgetInstance(LunaUnitConversion::class);
    $formatted2 = AssetsAccountIntegration::formatAccountBalance(1234.567, $metadata);
    expect($formatted2)->toBe('¥1,234.57');
    
    // 没有货币信息
    $formatted3 = AssetsAccountIntegration::formatAccountBalance(1234.567, []);
    expect($formatted3)->toBe('1,234.57');
});

test('完整的多币种账户使用流程模拟', function () {
    // 1. 模拟多币种账户配置
    $currencies = ['USD', 'CNY'];
    $accountConfigs = [];
    
    foreach ($currencies as $currencyCode) {
        $currency = $this->unitConversion->getUnit($currencyCode, 'currency');
        if ($currency) {
            $accountConfigs[$currencyCode] = [
                'name' => 'wallet_' . strtolower($currencyCode),
                'display_name' => '钱包 - ' . $currency->display_name,
                'metadata' => [
                    'currency' => [
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                        'display_name' => $currency->display_name,
                        'precision' => $currency->precision,
                    ]
                ]
            ];
        }
    }
    
    // 2. 模拟余额
    $usdBalance = 100.0;
    
    // 3. 转换显示
    $cnyEquivalent = AssetsAccountIntegration::convertBalance(
        $usdBalance,
        'USD',
        'CNY'
    );
    expect($cnyEquivalent)->toBe(700.0);
    
    // 4. 格式化显示
    $formattedUsd = AssetsAccountIntegration::formatAccountBalance(
        $usdBalance,
        $accountConfigs['USD']['metadata']
    );
    expect($formattedUsd)->toBe('$100.00');
});