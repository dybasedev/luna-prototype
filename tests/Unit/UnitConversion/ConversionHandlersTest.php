<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\ConditionalRateHandler;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\DynamicRateHandler;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\FixedRateHandler;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionConfigure;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 创建配置
    $this->configure = LunaUnitConversionConfigure::create()->build();
    
    // 创建组件实例
    $this->unitConversion = new LunaUnitConversion(
        $this->configure,
        app('cache.store'),
        app(LunaHandler::class)
    );
    
    // 创建测试单位
    $this->usd = $this->unitConversion->createUnit('currency', 'USD', [
        'symbol' => '$',
        'base_value' => 1.0,
        'is_base' => true,
        'precision' => 2,
    ]);
    
    $this->cny = $this->unitConversion->createUnit('currency', 'CNY', [
        'symbol' => '¥',
        'base_value' => 7.0,
        'precision' => 2,
    ]);
    
    $this->eur = $this->unitConversion->createUnit('currency', 'EUR', [
        'symbol' => '€',
        'base_value' => 0.85,
        'precision' => 2,
    ]);
});

test('固定比例处理器基本转换', function () {
    $handler = new FixedRateHandler();
    $context = new ConversionContext();
    
    $result = $handler->convert($this->usd, $this->cny, 100, $context);
    
    expect($result->getFromAmount())->toBe(100.0);
    expect($result->getToAmount())->toBe(700.0);
    expect($result->getRate())->toBe(7.0);
    expect($result->getFee())->toBe(0.0);
});

test('固定比例处理器带手续费', function () {
    $handler = new FixedRateHandler();
    $handler->withConfig([
        'fee' => [
            'fixed' => 5,
            'percentage' => 1, // 1%
        ],
    ]);
    
    $context = new ConversionContext(['calculate_fee' => true]);
    $result = $handler->convert($this->usd, $this->cny, 100, $context);
    
    // 转换金额: 100 * 7 = 700
    // 手续费: 5 + (700 * 0.01) = 5 + 7 = 12
    expect($result->getToAmount())->toBe(700.0);
    expect($result->getFee())->toBe(12.0);
    expect($result->getNetAmount())->toBe(688.0);
});

test('固定比例处理器手续费限制', function () {
    $handler = new FixedRateHandler();
    $handler->withConfig([
        'fee' => [
            'percentage' => 10, // 10%
            'min' => 20,
            'max' => 50,
        ],
    ]);
    
    $context = new ConversionContext(['calculate_fee' => true]);
    
    // 小额转换，手续费应该是最小值
    $result1 = $handler->convert($this->usd, $this->cny, 10, $context);
    expect($result1->getFee())->toBe(20.0); // 最小值
    
    // 大额转换，手续费应该是最大值
    $result2 = $handler->convert($this->usd, $this->cny, 1000, $context);
    expect($result2->getFee())->toBe(50.0); // 最大值
});

test('动态比例处理器从API获取汇率', function () {
    // 模拟HTTP响应
    Http::fake([
        'api.example.com/*' => Http::response(['rate' => 7.25], 200),
    ]);
    
    $handler = new DynamicRateHandler();
    $handler->withConfig([
        'source' => 'api',
        'api_url' => 'https://api.example.com/rate',
        'cache_duration' => 60,
    ]);
    
    $context = new ConversionContext();
    $result = $handler->convert($this->usd, $this->cny, 100, $context);
    
    expect($result->getRate())->toBe(7.25);
    expect($result->getToAmount())->toBe(725.0);
});

test('动态比例处理器从数据库获取汇率', function () {
    // 创建汇率表
    \DB::statement('CREATE TABLE exchange_rates (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        from_code VARCHAR(10),
        to_code VARCHAR(10),
        rate DECIMAL(20,10)
    )');
    
    \DB::table('exchange_rates')->insert([
        'from_code' => 'USD',
        'to_code' => 'CNY',
        'rate' => 7.15,
    ]);
    
    $handler = new DynamicRateHandler();
    $handler->withConfig([
        'source' => 'database',
        'rate_table' => 'exchange_rates',
    ]);
    
    $context = new ConversionContext();
    $result = $handler->convert($this->usd, $this->cny, 100, $context);
    
    expect($result->getRate())->toBe(7.15);
    expect($result->getToAmount())->toBe(715.0);
});

test('动态比例处理器从回调获取汇率', function () {
    $handler = new DynamicRateHandler();
    $handler->withConfig([
        'source' => 'callback',
        'callback' => function (UnitDefinition $from, UnitDefinition $to, ConversionContext $context) {
            // 模拟根据用户等级返回不同汇率
            $userLevel = $context->getParameter('user_level', 'normal');
            $baseRate = $to->base_value / $from->base_value;
            
            return match ($userLevel) {
                'vip' => $baseRate * 1.02, // VIP用户更优惠
                'premium' => $baseRate * 1.01,
                default => $baseRate,
            };
        },
    ]);
    
    // 普通用户
    $context1 = new ConversionContext(['parameters' => ['user_level' => 'normal']]);
    $result1 = $handler->convert($this->usd, $this->cny, 100, $context1);
    expect($result1->getRate())->toBe(7.0);
    
    // VIP用户
    $context2 = new ConversionContext(['parameters' => ['user_level' => 'vip']]);
    $result2 = $handler->convert($this->usd, $this->cny, 100, $context2);
    expect(round($result2->getRate(), 2))->toBe(7.14); // 7.0 * 1.02
});

test('条件化处理器根据条件应用不同规则', function () {
    $handler = new ConditionalRateHandler();
    $handler->withConfig([
        'rules' => [
            [
                'name' => 'vip_rule',
                'conditions' => [
                    ['type' => 'parameter', 'field' => 'user_level', 'operator' => '=', 'value' => 'vip'],
                ],
                'rate_multiplier' => 0.98, // VIP享受98折
                'fee_percentage' => 0.5, // 0.5%手续费
            ],
            [
                'name' => 'weekend_rule',
                'conditions' => [
                    ['type' => 'date', 'field' => 'is_weekend', 'operator' => '=', 'value' => true],
                ],
                'rate_adjustment' => 0.1, // 周末加收
                'fee_fixed' => 10,
            ],
        ],
        'default_rule' => [
            'name' => 'default',
            'fee_percentage' => 1,
        ],
    ]);
    
    // VIP用户
    $vipContext = ConversionContext::make([
        'parameters' => ['user_level' => 'vip'],
        'calculate_fee' => true,
    ]);
    
    $vipResult = $handler->convert($this->usd, $this->cny, 100, $vipContext);
    expect(round($vipResult->getRate(), 2))->toBe(6.86); // 7.0 * 0.98
    expect($vipResult->getToAmount())->toBe(686.0);
    expect($vipResult->getFee())->toBe(3.43); // 686 * 0.005
});

test('条件化处理器的复杂条件判断', function () {
    $handler = new ConditionalRateHandler();
    $handler->withConfig([
        'rules' => [
            [
                'name' => 'large_amount_vip',
                'conditions' => [
                    ['type' => 'parameter', 'field' => 'amount', 'operator' => '>=', 'value' => 1000],
                    ['type' => 'parameter', 'field' => 'user_level', 'operator' => 'in', 'value' => ['vip', 'premium']],
                ],
                'fee_percentage' => 0.3,
                'fee_max' => 50,
            ],
        ],
        'default_rule' => [
            'fee_percentage' => 1,
        ],
    ]);
    
    $context = ConversionContext::make([
        'parameters' => [
            'amount' => 1500,
            'user_level' => 'vip',
        ],
        'calculate_fee' => true,
    ]);
    
    $result = $handler->convert($this->usd, $this->cny, 1500, $context);
    
    // 转换金额: 1500 * 7 = 10500
    // 手续费: 10500 * 0.003 = 31.5
    expect($result->getFee())->toBe(31.5);
});

test('创建自定义转换规则', function () {
    // 创建处理器记录
    $handler = new Handler();
    $handler->id = 9001;
    $handler->name = FixedRateHandler::class;
    $handler->group_id = hash_code('unit-conversions');
    $handler->display_name = '固定比例转换';
    $handler->handler = FixedRateHandler::class;
    $handler->config = [];
    $handler->save();
    
    // 创建跨类别单位
    $meter = $this->unitConversion->createUnit('length', 'm', ['base_value' => 1.0, 'is_base' => true]);
    $second = $this->unitConversion->createUnit('time', 's', ['base_value' => 1.0, 'is_base' => true]);
    
    // 创建自定义规则（例如：速度单位）
    $rule = $this->unitConversion->createConversionRule(
        $meter,
        $second,
        FixedRateHandler::class,
        ['rates' => ['m' => ['s' => 0.1]]], // 假设的转换率
        100 // 高优先级
    );
    
    expect($rule)->not->toBeNull();
    expect($rule->priority)->toBe(100);
    expect($rule->config)->toBe(['rates' => ['m' => ['s' => 0.1]]]);
});