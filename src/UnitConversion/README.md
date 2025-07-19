# Luna Unit Conversion 单位转换组件

Luna Unit Conversion 是一个灵活、可扩展的单位转换组件，支持多种单位类型（货币、长度、重量、体积等）之间的转换，并提供动态转换率、条件化转换、手续费计算等高级功能。

## 核心特性

- **多种单位类型支持**：货币、长度、重量、体积、面积、时间、温度等
- **灵活的转换机制**：固定比例、动态比例、条件化转换
- **扩展性强**：通过处理器机制支持自定义转换逻辑
- **手续费支持**：支持固定费用、百分比费用、阶梯费用等
- **事件驱动**：通过事件机制支持外部系统记录转换历史
- **缓存优化**：支持转换率缓存，提高性能
- **与其他组件集成**：特别是与 AssetsAccount 组件的无缝集成

## 快速开始

### 1. 注册组件

在 `AppServiceProvider` 中注册组件：

```php
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionConfigure;

public function customRegister(): void
{
    $this->registerModule(
        LunaUnitConversionConfigure::create()->build()
    );
}
```

### 2. 发布迁移文件

运行以下命令发布迁移文件：

```bash
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionServiceProvider"
```

然后运行迁移：

```bash
php artisan migrate
```

### 3. 基本使用

```php
$unitConversion = luna_unit_conversion();

// 创建货币单位
$unitConversion->createUnit('currency', 'USD', [
    'symbol' => '$',
    'display_name' => '美元',
    'base_value' => 1.0,
    'is_base' => true,
]);

$unitConversion->createUnit('currency', 'CNY', [
    'symbol' => '¥',
    'display_name' => '人民币',
    'base_value' => 7.0,
]);

// 进行转换
$result = $unitConversion->convert('USD', 'CNY', 100);
echo $result->getToAmount(); // 700

// 使用辅助函数快速转换
$amount = luna_convert_unit('USD', 'CNY', 100);
echo $amount; // 700
```

### 4. 初始化预定义数据

组件提供了常用单位的预定义数据：

```php
$unitConversion->initializePredefinedData();
```

这将创建以下预定义类别和单位：
- 货币：USD、CNY、EUR
- 长度：m、km、cm
- 重量：kg、g、t
- 以及其他常用类别

## 单位管理

### 创建单位类别

```php
$category = $unitConversion->createCategory('digital_currency', [
    'display_name' => '数字货币',
    'description' => '各种数字货币单位',
]);
```

### 创建单位定义

```php
$bitcoin = $unitConversion->createUnit('digital_currency', 'BTC', [
    'symbol' => '₿',
    'display_name' => '比特币',
    'precision' => 8, // 8位小数
    'base_value' => 1.0,
    'is_base' => true,
]);

$ethereum = $unitConversion->createUnit('digital_currency', 'ETH', [
    'symbol' => 'Ξ',
    'display_name' => '以太坊',
    'precision' => 18,
    'base_value' => 0.05, // 1 BTC = 20 ETH (示例)
]);
```

### 获取单位信息

```php
// 获取单位
$unit = $unitConversion->getUnit('USD');

// 获取特定类别的单位
$unit = $unitConversion->getUnit('USD', 'currency');

// 获取类别
$category = $unitConversion->getCategory('currency');

// 获取所有活跃类别
$categories = $unitConversion->getActiveCategories();
```

## 转换功能

### 基本转换

```php
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;

// 简单转换
$result = $unitConversion->convert('m', 'km', 1000);
echo $result->getToAmount(); // 1

// 带上下文的转换
$context = ConversionContext::make([
    'should_log' => true, // 记录转换日志
    'calculate_fee' => true, // 计算手续费
    'parameters' => [
        'user_level' => 'vip',
    ],
]);

$result = $unitConversion->convert('USD', 'CNY', 100, $context);
```

### 批量转换

```php
$conversions = [
    'usd_to_cny' => ['from' => 'USD', 'to' => 'CNY', 'amount' => 100],
    'eur_to_usd' => ['from' => 'EUR', 'to' => 'USD', 'amount' => 85],
];

$results = $unitConversion->batchConvert($conversions);
```

### 获取转换率

```php
$rate = $unitConversion->getRate('USD', 'CNY');
echo $rate; // 7.0
```

### 转换结果处理

```php
$result = $unitConversion->convert('USD', 'CNY', 100);

// 获取各种信息
echo $result->getFromAmount();     // 100
echo $result->getToAmount();       // 700
echo $result->getRate();           // 7
echo $result->getFee();            // 0 (如果有手续费)
echo $result->getNetAmount();      // 700 (扣除手续费后)

// 格式化输出
echo $result->formatFromAmount();  // $100.00
echo $result->formatToAmount();    // ¥700.00
echo $result->getDescription();    // $100.00 → ¥700.00
```

## 转换处理器

### 1. 固定比例处理器（FixedRateHandler）

适用于同类别单位之间的标准转换：

```php
use Dybasedev\LunaPrototype\UnitConversion\Handlers\FixedRateHandler;

// 配置示例
$config = [
    'rates' => [
        'USD' => ['EUR' => 0.85], // 自定义汇率
    ],
    'fee' => [
        'fixed' => 5,        // 固定费用
        'percentage' => 1,   // 百分比费用
        'min' => 10,         // 最小费用
        'max' => 100,        // 最大费用
    ],
];
```

### 2. 动态比例处理器（DynamicRateHandler）

支持从外部数据源获取实时转换率：

```php
use Dybasedev\LunaPrototype\UnitConversion\Handlers\DynamicRateHandler;

// 从API获取
$config = [
    'source' => 'api',
    'api_url' => 'https://api.example.com/rates',
    'api_key' => 'your-api-key',
    'rate_path' => 'data.rate', // JSON路径
    'cache_duration' => 300, // 缓存5分钟
];

// 从数据库获取
$config = [
    'source' => 'database',
    'rate_table' => 'exchange_rates',
    'from_column' => 'from_currency',
    'to_column' => 'to_currency',
    'rate_column' => 'rate',
];

// 从回调函数获取
$config = [
    'source' => 'callback',
    'callback' => function ($fromUnit, $toUnit, $context) {
        // 自定义逻辑
        return 7.25;
    },
];
```

### 3. 条件化处理器（ConditionalRateHandler）

根据不同条件应用不同的转换规则：

```php
use Dybasedev\LunaPrototype\UnitConversion\Handlers\ConditionalRateHandler;

$config = [
    'rules' => [
        [
            'name' => 'vip_rule',
            'conditions' => [
                ['type' => 'parameter', 'field' => 'user_level', 'operator' => '=', 'value' => 'vip'],
            ],
            'rate_multiplier' => 0.98, // VIP享受98折
            'fee_percentage' => 0.5,
        ],
        [
            'name' => 'large_amount',
            'conditions' => [
                ['type' => 'parameter', 'field' => 'amount', 'operator' => '>=', 'value' => 10000],
            ],
            'fee_percentage' => 0.3,
            'fee_max' => 50,
        ],
    ],
    'default_rule' => [
        'fee_percentage' => 1,
    ],
];
```

## 自定义转换处理器

创建自定义处理器来实现特殊的转换逻辑：

```php
use Dybasedev\LunaPrototype\UnitConversion\Handlers\UnitConversionHandler;

class TemperatureConversionHandler extends UnitConversionHandler
{
    public function handlerName(): string
    {
        return '温度转换';
    }
    
    public function handlerDescription(): string
    {
        return '处理摄氏度、华氏度、开尔文之间的转换';
    }
    
    public function convert($fromUnit, $toUnit, $amount, $context): ConversionResult
    {
        $celsius = $this->toCelsius($fromUnit, $amount);
        $result = $this->fromCelsius($toUnit, $celsius);
        
        return new ConversionResult(
            $fromUnit,
            $toUnit,
            $amount,
            $result,
            1.0, // 温度转换没有"汇率"概念
            0    // 无手续费
        );
    }
    
    private function toCelsius($unit, $value)
    {
        return match($unit->code) {
            'C' => $value,
            'F' => ($value - 32) * 5/9,
            'K' => $value - 273.15,
        };
    }
    
    private function fromCelsius($unit, $celsius)
    {
        return match($unit->code) {
            'C' => $celsius,
            'F' => $celsius * 9/5 + 32,
            'K' => $celsius + 273.15,
        };
    }
}
```

注册自定义处理器：

```php
// 在 AppServiceProvider 中
$this->extendModule(function() {
    return LunaHandlerConfigure::create()
        ->group('unit-conversions', '单位转换处理器', function($register) {
            $register->handler(TemperatureConversionHandler::class);
        })
        ->build();
});
```

## 与 AssetsAccount 集成

Unit Conversion 组件可以与 AssetsAccount 组件无缝集成，提供多币种账户支持：

```php
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccountIntegration;

// 检查组件是否可用
if (AssetsAccountIntegration::isAvailable()) {
    // 创建多币种子账户
    $accounts = AssetsAccountIntegration::createMultiCurrencyAccounts(
        $assetsAccount,
        'wallet',
        ['USD', 'CNY', 'EUR'],
        [
            'display_name' => '多币种钱包',
            'handler_class' => WalletHandler::class,
        ]
    );
    
    // 转换账户余额
    $cnyAmount = AssetsAccountIntegration::convertBalance(
        100,    // 金额
        'USD',  // 源货币
        'CNY'   // 目标货币
    );
    
    // 格式化显示
    $formatted = AssetsAccountIntegration::formatAccountBalance(
        1234.56,
        $account->metadata
    );
}
```

## 转换事件

组件会在转换完成后触发 `ConversionCompleted` 事件，你可以监听此事件来实现日志记录或其他业务逻辑：

```php
use Dybasedev\LunaPrototype\UnitConversion\Events\ConversionCompleted;

// 在 EventServiceProvider 中注册监听器
protected $listen = [
    ConversionCompleted::class => [
        \App\Listeners\RecordConversionHistory::class,
    ],
];

// 监听器示例
class RecordConversionHistory
{
    public function handle(ConversionCompleted $event)
    {
        // 获取转换摘要
        $summary = $event->getSummary();
        
        // 根据业务需求记录转换历史
        ConversionHistory::create([
            'user_id' => auth()->id(),
            'order_id' => $event->context->getParameter('order_id'),
            'from_unit' => $summary['from']['unit_code'],
            'to_unit' => $summary['to']['unit_code'],
            'from_amount' => $summary['from']['amount'],
            'to_amount' => $summary['to']['amount'],
            'rate' => $summary['rate'],
            'fee' => $summary['fee'],
            'metadata' => $summary,
        ]);
    }
}
```

## 配置选项

```php
LunaUnitConversionConfigure::create()
    // 替换模型类
    ->useUnitCategoryModel(CustomUnitCategory::class)
    ->useUnitDefinitionModel(CustomUnitDefinition::class)
    
    // 启用/禁用事件
    ->enableEvents(true)
    
    // 设置默认缓存时间
    ->setDefaultCacheDuration(3600)
    
    // 添加自定义预定义类别
    ->addPredefinedCategory('energy', [
        'display_name' => '能量',
        'description' => '能量单位',
    ])
    
    ->build();
```

## 最佳实践

1. **合理使用缓存**：对于动态汇率，设置适当的缓存时间
2. **处理转换失败**：始终检查转换结果，处理可能的异常
3. **精度处理**：注意不同单位的精度要求
4. **手续费透明**：清晰地向用户展示手续费信息
5. **事件审计**：对于金融相关的转换，通过监听事件实现日志记录

## 常见使用场景

1. **多币种电商平台**：商品价格的多币种显示和结算
2. **国际汇款系统**：实时汇率转换和手续费计算
3. **物流系统**：重量、体积、距离单位的转换
4. **科学计算**：各种度量单位之间的精确转换
5. **加密货币交易**：数字货币之间的兑换

## API 参考

### 主要类

- `LunaUnitConversion`：组件主类
- `LunaUnitConversionConfigure`：配置类
- `ConversionContext`：转换上下文
- `ConversionResult`：转换结果
- `UnitConversionHandler`：处理器基类

### 辅助函数

- `luna_unit_conversion()`：获取组件实例
- `luna_convert_unit()`：快速单位转换
- `luna_format_unit_value()`：格式化单位值