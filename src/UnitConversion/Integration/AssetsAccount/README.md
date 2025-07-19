# UnitConversion 与 AssetsAccount 集成

本文档介绍如何使用 UnitConversion 组件与 AssetsAccount 组件的集成功能，实现多币种账户管理、跨币种转账、自动汇率转换等功能。

## 核心功能

### 1. 多币种账户转账
- 支持不同单位（如不同货币）账户之间的自动转换
- 转换过程中记录原始金额、汇率、手续费等详细信息
- 支持手续费从转出方或转入方扣除

### 2. 转换上下文记录
- 所有转换信息记录在账户变更日志的 `payload._unit_conversion` 字段中
- 保留原始金额用于对账和审计
- 记录转换时间、汇率、手续费等关键信息

### 3. 灵活的手续费处理
- 支持固定费用或百分比费用
- 可配置手续费扣除方（转出方或转入方）
- 手续费扣除作为独立的账户操作记录

### 4. 扩展性设计
- AssetsAccount 组件支持自定义操作类
- UnitConversion 组件设计为可被其他组件集成
- 支持自定义转换处理器

## 快速开始

### 1. 配置 AssetsAccount 使用转换感知的操作类

```php
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount\ConversionAwareAccountOperations;

// 在 AppServiceProvider 中配置
$this->registerModule(
    LunaAssetsAccountConfigure::create()
        ->useAccountOperationClass(ConversionAwareAccountOperations::class)
        ->build()
);
```

### 2. 创建多币种账户

```php
$assetsAccount = luna_assets_account();

// 创建 USD 账户
$usdAccount = $assetsAccount->createAccountType(
    'wallet_usd',
    'currency_handler',
    'USD 钱包',
    '美元账户'
);

// 创建 CNY 账户
$cnyAccount = $assetsAccount->createAccountType(
    'wallet_cny',
    'currency_handler',
    'CNY 钱包',
    '人民币账户'
);
```

### 3. 执行跨币种转账

```php
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;

// 创建支持转换的操作对象
$operation = luna_conversion_aware_operations();

// 配置转换上下文（可选）
$context = ConversionContext::make([
    'calculate_fee' => true,
    'parameters' => [
        'fee_percentage' => 0.01,  // 1% 手续费
        'user_level' => 'vip',     // 用于条件化处理器
    ]
]);

// 执行转账操作
$operation->operation(
    luna_unit_conversion_transfer()
        ->from($user, 'wallet_usd')    // 从 USD 账户
        ->fromAvailable()               // 转出可用余额
        ->fromUnit('USD')               // 源单位
        ->to($user, 'wallet_cny')       // 到 CNY 账户
        ->toAvailable()                 // 转入可用余额
        ->toUnit('CNY')                 // 目标单位
        ->event('currency_exchange')    // 事件标识
        ->amount(100)                   // 转账金额（源单位）
        ->feeFromSender()               // 手续费从转出方扣除
        ->withConversionContext($context)
);

$operation->submit();
```

## 详细使用说明

### ConversionAwareAccountOperations

这是扩展的账户操作类，支持处理带有单位转换信息的操作。

#### 主要特性：
- 继承自标准的 `AccountOperations` 类，保持完全兼容
- 自动识别并处理 `ConversionAwareOperationBuilder`
- 将转换信息注入到操作的 payload 中

#### 使用方式：
```php
// 方式1：通过配置设置为默认操作类
$configure = LunaAssetsAccountConfigure::create()
    ->useAccountOperationClass(ConversionAwareAccountOperations::class)
    ->build();

// 方式2：创建时指定
$operation = $assetsAccount->createAccountOperation(ConversionAwareAccountOperations::class);

// 方式3：使用辅助函数
$operation = luna_conversion_aware_operations();
```

### UnitConversionTransferBuilder

专门用于构建带单位转换的转账操作。

#### 主要方法：

```php
// 设置单位
->fromUnit('USD')              // 源账户单位
->toUnit('CNY')                // 目标账户单位

// 设置转换上下文
->withConversionContext($context)

// 设置手续费扣除方
->feeFromSender()              // 从转出方扣除（默认）
->feeFromReceiver()            // 从转入方扣除

// 其他方法继承自 AccountTransferOperationBuilder
->from($user, 'account_type')
->to($user, 'account_type')
->amount(100)
->event('event_name')
->payload(['custom' => 'data'])
```

### 转换信息结构

转换信息存储在账户变更日志的 `payload._unit_conversion` 字段中：

```php
[
    '_unit_conversion' => [
        'from_unit' => 'USD',           // 源单位
        'to_unit' => 'CNY',             // 目标单位
        'original_amount' => 100.0,     // 原始金额
        'converted_amount' => 700.0,    // 转换后金额
        'rate' => 7.0,                  // 汇率
        'fee' => 1.0,                   // 手续费
        'fee_deduct_from' => 'from',    // 手续费扣除方
        'conversion_time' => '2024-01-01T00:00:00+00:00',
        'context_params' => [           // 转换上下文参数
            'fee_percentage' => 0.01,
            'user_level' => 'vip'
        ]
    ],
    // ... 其他 payload 数据
]
```

### 手续费处理

手续费会作为独立的账户操作记录：

```php
// 手续费扣除记录
[
    'event_id' => hash_code('unit_conversion_fee'),
    'amount' => -1.0,  // 负数表示扣除
    'payload' => [
        '_unit_conversion' => [
            'type' => 'conversion_fee',
            'fee_amount' => 1.0,
            'fee_unit' => 'USD',
            'related_conversion' => [...]  // 关联的转换信息
        ]
    ]
]
```

## 高级功能

### 1. 自定义转换处理器

可以创建自定义的转换处理器来实现特殊的汇率逻辑：

```php
// 创建转换规则，使用自定义处理器
$unitConversion->createConversionRule(
    'USD',
    'CNY', 
    MyCustomRateHandler::class,
    ['api_key' => 'xxx'],  // 处理器配置
    100  // 优先级
);
```

### 2. 同账户不同余额类型转换

支持同一账户持有人的不同单位子账户之间的转换：

```php
// 同一用户的不同货币钱包间转换
$operation->operation(
    luna_unit_conversion_transfer()
        ->from($user, 'wallet_usd')
        ->to($user, 'wallet_eur')
        ->fromUnit('USD')
        ->toUnit('EUR')
        ->amount(100)
);
```

### 3. 批量转换操作

可以在一个事务中执行多个转换操作：

```php
$operation = luna_conversion_aware_operations();

// 添加多个转换操作
$operation->operation($transfer1);
$operation->operation($transfer2);
$operation->operation($transfer3);

// 一次性提交
$operation->submit();
```

## 兼容性说明

### 向后兼容
- `ConversionAwareAccountOperations` 完全兼容标准的 `AccountOperations`
- 不使用单位转换功能时，行为与原有系统完全一致
- 转换信息存储在独立的 payload 字段中，不影响其他数据

### 数据库兼容
- 无需修改 AssetsAccount 组件的表结构
- 转换信息存储在已有的 JSON 字段中
- 可以在已有系统中无缝集成

## 最佳实践

### 1. 账户类型设计
为不同币种创建独立的账户类型，便于管理和统计：
```php
wallet_usd  // USD 钱包
wallet_cny  // CNY 钱包
wallet_eur  // EUR 钱包
```

### 2. 事件命名
使用清晰的事件名称标识不同类型的操作：
```php
'currency_exchange'      // 货币兑换
'cross_border_payment'   // 跨境支付
'currency_conversion'    // 币种转换
```

### 3. 错误处理
```php
try {
    $operation->submit();
} catch (LunaException $e) {
    // 处理转换失败、余额不足等错误
    Log::error('Currency conversion failed', [
        'error' => $e->getMessage(),
        'data' => $e->getData()
    ]);
}
```

### 4. 审计和对账
利用记录的原始金额信息进行对账：
```php
$logs = AssetsAccountChangeLog::where('account_id', $accountId)
    ->whereJsonContains('payload._unit_conversion', ['from_unit' => 'USD'])
    ->get();

foreach ($logs as $log) {
    $conversion = $log->payload['_unit_conversion'];
    // 使用 original_amount 进行对账
    $originalUSD = $conversion['original_amount'];
    $convertedCNY = $conversion['converted_amount'];
    $rate = $conversion['rate'];
}
```

## 示例场景

### 场景1：用户货币兑换
```php
// 用户将 USD 兑换为 CNY
$operation = luna_conversion_aware_operations();
$operation->operation(
    luna_unit_conversion_transfer()
        ->from($user, 'wallet_usd')
        ->to($user, 'wallet_cny')
        ->fromUnit('USD')
        ->toUnit('CNY')
        ->amount(1000)
        ->event('user_exchange')
        ->payload(['reason' => '旅游换汇'])
);
$operation->submit();
```

### 场景2：跨境支付
```php
// 用户 A 向用户 B 跨境转账
$operation = luna_conversion_aware_operations();
$operation->operation(
    luna_unit_conversion_transfer()
        ->from($userA, 'wallet_usd')
        ->to($userB, 'wallet_cny')
        ->fromUnit('USD')
        ->toUnit('CNY')
        ->amount(500)
        ->event('cross_border_transfer')
        ->feeFromSender()
        ->withConversionContext(
            ConversionContext::make([
                'calculate_fee' => true,
                'parameters' => [
                    'transfer_type' => 'international',
                    'fee_percentage' => 0.02  // 2% 跨境费
                ]
            ])
        )
);
$operation->submit();
```

### 场景3：自动汇率更新
```php
// 使用动态汇率处理器
$unitConversion->createConversionRule(
    'USD',
    'CNY',
    DynamicRateHandler::class,
    [
        'api_endpoint' => 'https://api.exchangerate.com/latest',
        'cache_duration' => 300  // 5分钟缓存
    ],
    100
);

// 之后的转换会自动使用最新汇率
```

## 故障排除

### 问题：单位转换模块不可用
```php
// 检查模块是否已注册
if (!luna_unit_conversion()) {
    throw new \RuntimeException('UnitConversion module not registered');
}
```

### 问题：转换信息未记录
确保使用了 `ConversionAwareAccountOperations` 和 `UnitConversionTransferBuilder`：
```php
// 正确
$operation = luna_conversion_aware_operations();
$operation->operation(luna_unit_conversion_transfer()->...);

// 错误（不会记录转换信息）
$operation = luna_assets_account()->createAccountOperation();
$operation->operation(luna_account_transfer()->...);
```

### 问题：手续费计算不正确
检查转换上下文的配置：
```php
$context = ConversionContext::make([
    'calculate_fee' => true,  // 必须启用
    'parameters' => [
        'fee_percentage' => 0.01,
        'min_fee' => 1.0,
        'max_fee' => 100.0
    ]
]);
```

## 总结

UnitConversion 与 AssetsAccount 的集成提供了强大而灵活的多币种账户管理功能。通过合理的设计，既保证了系统的扩展性，又维持了与现有系统的兼容性。这种集成方式也为其他组件的集成提供了参考模板。