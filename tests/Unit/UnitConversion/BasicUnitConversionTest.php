<?php

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionConfigure;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitCategory;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Dybasedev\LunaPrototype\UnitConversion\Attributes\CategoryAttributes;
use Dybasedev\LunaPrototype\UnitConversion\Attributes\UnitAttributes;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
});

test('可以创建单位类别', function () {
    $category = $this->unitConversion->createCategory('test_category', [
        'display_name' => '测试类别',
        'description' => '用于测试的类别',
    ]);
    
    expect($category)->toBeInstanceOf(UnitCategory::class);
    expect($category->name)->toBe('test_category');
    expect($category->display_name)->toBe('测试类别');
    expect($category->is_active)->toBeTrue();
});

test('可以使用 CategoryAttributes 创建单位类别', function () {
    $category = $this->unitConversion->createCategory('test_category', 
        CategoryAttributes::create()
            ->description('测试类别描述')
            ->active()
            ->config(['key' => 'value'])
    );
    
    expect($category)->toBeInstanceOf(UnitCategory::class);
    expect($category->name)->toBe('test_category');
    expect($category->description)->toBe('测试类别描述');
    expect($category->is_active)->toBeTrue();
    expect($category->config)->toBe(['key' => 'value']);
});

test('可以获取单位类别', function () {
    // 创建类别
    $this->unitConversion->createCategory('test_category', [
        'display_name' => '测试类别',
    ]);
    
    // 获取类别
    $category = $this->unitConversion->getCategory('test_category');
    
    expect($category)->not->toBeNull();
    expect($category->name)->toBe('test_category');
});

test('可以创建单位定义', function () {
    $unit = $this->unitConversion->createUnit('currency', 'TEST', [
        'symbol' => 'T',
        'display_name' => '测试币',
        'precision' => 2,
        'base_value' => 1.5,
    ]);
    
    expect($unit)->toBeInstanceOf(UnitDefinition::class);
    expect($unit->code)->toBe('TEST');
    expect($unit->symbol)->toBe('T');
    expect($unit->precision)->toBe(2);
    expect($unit->base_value)->toBe(1.5);
});

test('可以使用 UnitAttributes 创建单位定义', function () {
    $unit = $this->unitConversion->createUnit('currency', 'TEST', 
        UnitAttributes::create()
            ->symbol('T')
            ->displayName('测试币')
            ->precision(2)
            ->baseValue(1.5)
            ->formatTemplate('{symbol}{value}')
    );
    
    expect($unit)->toBeInstanceOf(UnitDefinition::class);
    expect($unit->code)->toBe('TEST');
    expect($unit->symbol)->toBe('T');
    expect($unit->display_name)->toBe('测试币');
    expect($unit->precision)->toBe(2);
    expect($unit->base_value)->toBe(1.5);
});

test('第一个单位自动成为基准单位', function () {
    $unit1 = $this->unitConversion->createUnit('new_category', 'UNIT1', [
        'display_name' => '单位1',
    ]);
    
    expect($unit1->is_base)->toBeTrue();
    expect($unit1->base_value)->toBe(1.0);
    
    $unit2 = $this->unitConversion->createUnit('new_category', 'UNIT2', [
        'display_name' => '单位2',
        'base_value' => 2.0,
    ]);
    
    expect($unit2->is_base)->toBeFalse();
    expect($unit2->base_value)->toBe(2.0);
});

test('可以获取单位定义', function () {
    // 创建单位
    $this->unitConversion->createUnit('currency', 'TEST', [
        'display_name' => '测试币',
    ]);
    
    // 获取单位
    $unit = $this->unitConversion->getUnit('TEST');
    expect($unit)->not->toBeNull();
    expect($unit->code)->toBe('TEST');
    
    // 通过类别获取
    $unit2 = $this->unitConversion->getUnit('TEST', 'currency');
    expect($unit2)->not->toBeNull();
    expect($unit2->id)->toBe($unit->id);
});

test('同类别单位之间的固定比例转换', function () {
    // 创建货币单位
    $usd = $this->unitConversion->createUnit('currency', 'USD', [
        'symbol' => '$',
        'display_name' => '美元',
        'base_value' => 1.0,
        'is_base' => true,
    ]);
    
    $cny = $this->unitConversion->createUnit('currency', 'CNY', [
        'symbol' => '¥',
        'display_name' => '人民币',
        'base_value' => 7.0,
    ]);
    
    // 转换 100 美元到人民币
    $result = $this->unitConversion->convert('USD', 'CNY', 100);
    
    expect($result->getFromAmount())->toBe(100.0);
    expect($result->getToAmount())->toBe(700.0);
    expect($result->getRate())->toBe(7.0);
    expect($result->getFee())->toBe(0.0);
});

test('单位值格式化', function () {
    $unit = $this->unitConversion->createUnit('currency', 'USD', [
        'symbol' => '$',
        'precision' => 2,
    ]);
    
    expect($unit->formatValue(1234.567))->toBe('$1,234.57');
    
    $unit2 = $this->unitConversion->createUnit('length', 'm', [
        'symbol' => 'm',
        'precision' => 1,
    ]);
    
    expect($unit2->formatValue(123.456))->toBe('m123.5');
});

test('转换上下文的使用', function () {
    // 创建单位
    $this->unitConversion->createUnit('currency', 'USD', [
        'base_value' => 1.0,
        'is_base' => true,
    ]);
    
    $this->unitConversion->createUnit('currency', 'CNY', [
        'base_value' => 7.0,
    ]);
    
    // 创建上下文
    $context = ConversionContext::make([
        'should_log' => false,
        'calculate_fee' => true,
        'parameters' => [
            'user_level' => 'vip',
        ],
    ]);
    
    $result = $this->unitConversion->convert('USD', 'CNY', 100, $context);
    
    expect($result)->not->toBeNull();
    expect($context->shouldLog())->toBeFalse();
    expect($context->shouldCalculateFee())->toBeTrue();
    expect($context->getParameter('user_level'))->toBe('vip');
});

test('批量转换', function () {
    // 创建单位
    $this->unitConversion->createUnit('currency', 'USD', ['base_value' => 1.0, 'is_base' => true]);
    $this->unitConversion->createUnit('currency', 'CNY', ['base_value' => 7.0]);
    $this->unitConversion->createUnit('currency', 'EUR', ['base_value' => 0.85]);
    
    $conversions = [
        'usd_to_cny' => ['from' => 'USD', 'to' => 'CNY', 'amount' => 100],
        'usd_to_eur' => ['from' => 'USD', 'to' => 'EUR', 'amount' => 100],
        'cny_to_eur' => ['from' => 'CNY', 'to' => 'EUR', 'amount' => 700],
    ];
    
    $results = $this->unitConversion->batchConvert($conversions);
    
    expect($results)->toHaveCount(3);
    expect($results['usd_to_cny'])->toBeInstanceOf(ConversionResult::class);
    expect($results['usd_to_cny']->getToAmount())->toBe(700.0);
    expect($results['usd_to_eur']->getToAmount())->toBe(85.0);
    expect($results['cny_to_eur']->getToAmount())->toBe(85.0);
});

test('获取转换率', function () {
    // 创建单位
    $this->unitConversion->createUnit('length', 'm', ['base_value' => 1.0, 'is_base' => true]);
    $this->unitConversion->createUnit('length', 'km', ['base_value' => 1000.0]);
    $this->unitConversion->createUnit('length', 'cm', ['base_value' => 0.01]);
    
    expect($this->unitConversion->getRate('m', 'km'))->toBe(1000.0);
    expect($this->unitConversion->getRate('km', 'm'))->toBe(0.001);
    expect($this->unitConversion->getRate('m', 'cm'))->toBe(0.01);
});

test('初始化预定义数据', function () {
    $this->unitConversion->initializePredefinedData();
    
    // 检查预定义类别
    expect($this->unitConversion->getCategory('currency'))->not->toBeNull();
    expect($this->unitConversion->getCategory('length'))->not->toBeNull();
    expect($this->unitConversion->getCategory('weight'))->not->toBeNull();
    
    // 检查预定义单位
    expect($this->unitConversion->getUnit('USD'))->not->toBeNull();
    expect($this->unitConversion->getUnit('CNY'))->not->toBeNull();
    expect($this->unitConversion->getUnit('m'))->not->toBeNull();
    expect($this->unitConversion->getUnit('kg'))->not->toBeNull();
});

test('辅助函数工作正常', function () {
    // 注册组件到容器
    app()->instance(LunaUnitConversion::class, $this->unitConversion);
    
    // 创建单位
    $this->unitConversion->createUnit('currency', 'USD', ['base_value' => 1.0, 'is_base' => true]);
    $this->unitConversion->createUnit('currency', 'CNY', ['base_value' => 7.0, 'symbol' => '¥']);
    
    // 测试快速转换
    $result = luna_convert_unit('USD', 'CNY', 100);
    expect($result)->toBe(700.0);
    
    // 测试格式化
    $formatted = luna_format_unit_value(1234.56, 'CNY');
    expect($formatted)->toBe('¥1,234.56');
});

test('批量获取单位定义', function () {
    // 创建多个单位
    $this->unitConversion->createUnit('currency', 'USD', ['display_name' => '美元']);
    $this->unitConversion->createUnit('currency', 'CNY', ['display_name' => '人民币']);
    $this->unitConversion->createUnit('currency', 'EUR', ['display_name' => '欧元']);
    $this->unitConversion->createUnit('length', 'm', ['display_name' => '米']);
    
    // 批量获取货币单位
    $units = $this->unitConversion->getUnits(['USD', 'CNY', 'EUR'], 'currency');
    expect($units)->toHaveCount(3);
    expect($units->pluck('code')->toArray())->toBe(['USD', 'CNY', 'EUR']);
    
    // 批量获取不同类别的单位
    $allUnits = $this->unitConversion->getUnits(['USD', 'm']);
    expect($allUnits)->toHaveCount(2);
});

test('获取所有单位类别（永久缓存）', function () {
    // 创建多个类别
    $this->unitConversion->createCategory('test1', ['display_name' => '测试1']);
    $this->unitConversion->createCategory('test2', ['display_name' => '测试2']);
    
    // 第一次调用，从数据库获取
    $categories1 = $this->unitConversion->getAllCategories();
    expect($categories1)->toHaveCount(2);
    
    // 创建新类别
    $this->unitConversion->createCategory('test3', ['display_name' => '测试3']);
    
    // 第二次调用，应该从缓存获取（永久缓存）
    $categories2 = $this->unitConversion->getAllCategories();
    expect($categories2)->toHaveCount(2); // 仍然是2个，因为使用了永久缓存
});

test('单位定义ID自动生成', function () {
    $category = $this->unitConversion->createCategory('test_category');
    $unit = $this->unitConversion->createUnit('test_category', 'TEST');
    
    // 验证ID是通过hash_code生成的
    $expectedId = hash_code('test_category:TEST');
    expect($unit->id)->toBe($expectedId);
    
    // 验证通过模型创建时也会自动生成ID
    $unitModel = $this->configure->unitDefinitionModel;
    $newUnit = new $unitModel([
        'category_id' => $category->id,
        'code' => 'TEST2',
        'display_name' => 'Test Unit 2',
        'is_active' => true,
    ]);
    $newUnit->save();
    
    expect($newUnit->id)->toBe(hash_code('test_category:TEST2'));
});

test('缓存机制正常工作', function () {
    // 创建类别和单位
    $this->unitConversion->createCategory('cache_test');
    $this->unitConversion->createUnit('cache_test', 'UNIT1');
    
    // 第一次获取，会查询数据库
    $category1 = $this->unitConversion->getCategory('cache_test');
    $unit1 = $this->unitConversion->getUnit('UNIT1', 'cache_test');
    
    expect($category1)->not->toBeNull();
    expect($unit1)->not->toBeNull();
    
    // 直接修改数据库（绕过缓存）
    $categoryModel = $this->configure->unitCategoryModel;
    $categoryModel::where('name', 'cache_test')->update(['display_name' => 'Modified']);
    
    // 第二次获取，应该从缓存获取（未修改的数据）
    $category2 = $this->unitConversion->getCategory('cache_test');
    expect($category2->display_name)->not->toBe('Modified');
    
    // 清除所有缓存
    $this->unitConversion->clearAllCache();
    
    // 第三次获取，会重新查询数据库
    $category3 = $this->unitConversion->getCategory('cache_test');
    expect($category3->display_name)->toBe('Modified');
});