# Trade 交易组件

Trade 组件提供了灵活的交易管理框架，支持多种交易流程和业务场景。

## 核心概念

### 1. Tradable（可交易对象）

实现了 `Tradable` 接口的对象可以被纳入交易流程中，例如产品、服务、虚拟商品等。

```php
interface Tradable
{
    public function getTradableId(): int|string;
    public function getTradableType(): string;
    public function getTradableName(): string;
    public function getTradablePrice(): float;
    public function isTradableAvailable(): bool;
    public function checkTradableStock(float $quantity): bool;
    // ...
}
```

### 2. TradeFlowHandler（交易流程处理器）

定义交易的具体流程，不同的业务场景可以实现不同的处理器。

```php
abstract class TradeFlowHandler extends BaseHandler
{
    abstract public function generatePreview(...): TransactionPreview;
    abstract public function createTransactionFromPreview(...): TradeTransaction;
    abstract public function handleStatusChange(...): bool;
    abstract public function completeTransaction(...): void;
    // ...
}
```

### 3. TradableHandler（可交易对象处理器）

为可交易对象提供价格计算、格式化、验证等功能。

```php
abstract class TradableHandler extends BaseHandler
{
    abstract public function calculatePrice(...): array;
    abstract public function formatPrice(...): string;
    abstract public function formatForDisplay(...): array;
    // ...
}
```

> **注意**：TradableHandler 不处理折扣等业务逻辑。详细使用指南请参考 [TradableHandler 使用指南](./TRADABLE_HANDLER_GUIDE.md)。

### 4. TransactionPreview（交易预览）

在创建交易前提供预览功能，使用灵活的修饰器模式处理金额变化。

```php
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\DiscountModifier;
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\ShippingFeeModifier;

$preview = new TransactionPreview($owner);
$preview->addItem($product, 2.0)
    ->addModifier(new DiscountModifier('member', '会员折扣', 10.0, 'percentage'))
    ->addModifier(new ShippingFeeModifier('standard', 10.0, 99.0));
```

> **注意**：TransactionPreview 使用修饰器模式处理金额变化。详细使用指南请参考 [TransactionPreview 使用指南](./TRANSACTION_PREVIEW_GUIDE.md)。

### 5. TransactionContext（交易上下文）

携带交易过程中的上下文信息。

```php
$context = TransactionContext::make()
    ->fromChannel('mobile_app')
    ->fromCampaign('summer_sale_2024')
    ->withParameters(['member_level' => 'vip']);
```

## 安装配置

### 1. 发布并运行迁移

```bash
# 发布迁移文件到项目
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\Trade\LunaTradeServiceProvider" --tag=luna-trade-migrations

# 运行迁移
php artisan migrate
```

这会创建以下数据表：
- `luna_trade_transactions` - 交易事务表
- `luna_trade_transaction_tradables` - 交易商品关联表
- `luna_trade_transaction_activities` - 交易活动日志表
- `luna_trade_order_sources` - 订单来源表

## 快速开始

### 1. 配置组件

```php
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;

// 配置 Trade 组件
$configure = LunaTradeConfigure::create()
    ->setDefaultTransactionNumberPrefix('ORD')
    ->enableExpiredCheck(true, 30)
    ->build();

// 通过 LunaHandler 注册交易流程处理器
luna_handler()->createEntityHandler(
    'trade',                            // 组名
    'standard',                         // 处理器名称
    StandardTradeFlowHandler::class,    // 处理器类
    null,                              // 配置（可选）
    'Standard trade flow'              // 描述
);
```

### 2. 创建交易

```php
// 获取交易管理实例
$trade = luna_trade();

// 创建单个商品的交易
$transaction = $trade->createTransaction(
    $user,                    // 交易所有者
    'standard',              // 使用标准交易流程
    $product,                // 可交易对象
    [
        'quantity' => 2,     // 数量
        'expire_minutes' => 30, // 30分钟后过期
    ]
);

// 创建多个商品的交易
$transaction = $trade->createTransaction(
    $user,
    'standard',
    [$product1, $product2],  // 多个可交易对象
    [
        'quantities' => [
            1 => 1,  // product1 的数量
            2 => 3,  // product2 的数量
        ]
    ]
);
```

### 3. 更新交易状态

```php
use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionStatus;

// 更新交易状态
$trade->updateTransactionStatus(
    $transaction,
    StandardTransactionStatus::Paid->getCode(),
    ['payment_method' => 'alipay']
);

// 完成交易
$trade->completeTransaction($transaction);

// 取消交易
$trade->cancelTransaction($transaction, '用户取消');
```

### 4. 查询交易

```php
// 查询用户的交易列表
$transactions = $trade->getOwnerTransactions(
    $user,
    [
        'status' => StandardTransactionStatus::Paid->getCode(),
        'date_from' => '2024-01-01',
        'date_to' => '2024-12-31',
    ],
    20  // 每页20条
);

// 或使用查询构建器
$transactions = luna_trade_query()
    ->forOwner($user)
    ->whereStatus(StandardTransactionStatus::Paid->getCode())
    ->whereDateBetween('2024-01-01', '2024-12-31')
    ->perPage(20)
    ->paginate();

// 根据交易号查询
$transaction = $trade->getTransactionByNumber('ORD20240101000112345');
```

## 交易预览流程

交易预览功能让用户在确认交易前查看详细信息：

```php
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\*;

// 1. 创建交易上下文
$context = TransactionContext::make()
    ->fromChannel('web')
    ->fromCampaign('new_year_sale', ['discount' => 0.1])
    ->withParameters(['member_discount' => 0.05]);

// 2. 生成交易预览
$handler = $trade->getFlowHandler('standard');
$preview = $handler->generatePreview($user, [$product1, $product2], $context);

// 3. 应用优惠券（使用修饰器）
$coupon = new CouponModifier('SAVE100', '新年优惠券', 100.0, 'fixed');
$preview->addModifier($coupon);

// 4. 添加运费
$shipping = new ShippingFeeModifier('standard', 10.0, 99.0);
$preview->addModifier($shipping);

// 5. 获取预览信息
$previewData = $preview->toArray();
// [
//     'base_amount' => 2099.00,      // 商品总价
//     'final_amount' => 1899.00,     // 最终金额
//     'modifiers' => [...],          // 应用的修饰器
//     'modifier_breakdown' => [...], // 修饰器影响明细
// ]

// 6. 用户确认后创建交易
$transaction = $handler->createTransactionFromPreview($preview, [
    'shipping_address' => '北京市朝阳区...',
    'remark' => '请尽快发货',
]);
```

## 标准交易流程状态

标准交易流程保持简洁，只包含核心状态：

```php
enum StandardTransactionStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';  // 待支付
    case PAID = 'paid';                        // 已支付
    case COMPLETED = 'completed';              // 已完成
    case CANCELED = 'canceled';                // 已取消
    case EXPIRED = 'expired';                  // 已过期
}
```

状态转换规则：

```
pending_payment -> paid / canceled / expired
paid -> completed / canceled
```

> **提示**：如果您需要更多状态（如发货、收货、退款等），请参考 [扩展状态指南](./Standard/ExtendStatus.md)。

## 自定义交易流程

创建自定义的交易流程处理器：

```php
use Dybasedev\LunaPrototype\Trade\TradeFlowHandler;

class CustomTradeFlowHandler extends TradeFlowHandler
{
    public function handlerName(): string
    {
        return 'custom';
    }
    
    public function handlerDescription(): string
    {
        return '自定义交易流程';
    }
    
    public function generatePreview(
        SessionHolder $owner,
        Tradable|array $tradables,
        ?TransactionContext $context = null
    ): TransactionPreview {
        // 实现自定义的预览生成逻辑
    }
    
    protected function getTransactionStatuses(): array
    {
        return [
            'pending' => ['code' => 1, 'label' => '待确认'],
            'processing' => ['code' => 10, 'label' => '进行中'],
            'completed' => ['code' => 100, 'label' => '已完成'],
            'canceled' => ['code' => -1, 'label' => '已取消'],
        ];
    }
    
    // 实现其他必要方法...
}
```

注册并使用：

```php
// 通过 LunaHandler 注册自定义处理器
luna_handler()->createEntityHandler(
    'trade',
    'custom',
    CustomTradeFlowHandler::class,
    null,
    'Custom trade flow'
);

// 使用自定义流程创建交易
$transaction = luna_trade()->createTransaction(
    $user,
    'custom',  // 使用自定义流程
    $tradable,
    $options
);
```

## 使用 TradableHandler

处理器提供了价格计算、验证等功能：

```php
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradableHandler;

$handler = new StandardTradableHandler();

// 计算价格（基础价格，不包含折扣）
$priceInfo = $handler->calculatePrice($product, 2.0, $context);
// [
//     'price' => 1798.00,
//     'origin_price' => 1798.00,
//     'unit_id' => null,
//     'metadata' => [...]
// ]

// 验证可交易对象
$validation = $handler->validate($product, 10.0, $context);
if (!$validation['valid']) {
    // 处理错误：$validation['message']
}

// 格式化价格显示
$formatted = $handler->formatPrice(1999.00);  // "¥1,999.00"
```

## 与其他组件集成

### 与 AssetsAccount 集成

在交易支付成功后的处理器中更新账户余额：

```php
class MyTradeFlowHandler extends StandardTradeFlowHandler
{
    protected function handlePaymentCompleted(
        Transaction $transaction,
        array $context
    ): void {
        parent::handlePaymentCompleted($transaction, $context);
        
        if ($context['payment_method'] === 'balance') {
            // 扣除买家账户余额
            luna_account_update()
                ->account($transaction->getOwner(), 'balance')
                ->available()
                ->event('trade_payment')
                ->decrease($transaction->amount)
                ->submit();
        }
    }
}
```

### 与 UnitConversion 集成

支持多币种交易：

```php
class MultiCurrencyHandler extends StandardTradableHandler
{
    public function calculatePrice(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        $result = parent::calculatePrice($tradable, $quantity, $context);
        
        // 货币转换
        if ($currency = $context?->getParameter('currency')) {
            $rate = luna_unit_conversion()->getConversionRate('CNY', $currency);
            $result['unit_price'] *= $rate;
            $result['amount'] *= $rate;
            $result['currency'] = $currency;
        }
        
        return $result;
    }
}
```

## 数据库结构

### luna_trade_transactions 表

存储交易主记录，包含交易状态、金额、所有者等核心信息。

### luna_trade_transaction_tradables 表

存储交易与可交易对象的关联关系，支持一个交易包含多个商品。

### luna_trade_tradables 表

标准的可交易对象表，可以直接使用或作为参考实现自定义的商品表。

## 构建器模式

### TradeOperationBuilder

提供链式调用接口来构建和执行交易操作：

```php
// 使用标准交易操作构建器
$result = luna_trade_operation()
    ->transaction($transaction)  // 或传入交易ID
    ->withContext([
        'payment_method' => 'wechat',
        'payment_no' => 'WX123456',
    ])
    ->updateStatus(StandardTransactionStatus::Paid->getCode());

if ($result->isSuccess()) {
    // 处理成功
}

// 完成交易
luna_trade_operation()
    ->transaction($transaction)
    ->complete();

// 取消交易
luna_trade_operation()
    ->transaction($transaction)
    ->cancel('用户取消');
```

### TradeQueryBuilder

提供链式调用接口来构建交易查询：

```php
// 使用标准交易查询构建器
$transactions = luna_trade_query()
    ->forOwner($user)
    ->whereStatus(StandardTransactionStatus::Paid->getCode())
    ->onlyCompleted()  // 或者 onlyPending()
    ->whereDateBetween('2024-01-01', '2024-12-31')
    ->perPage(10)
    ->paginate();

// 获取第一条记录
$firstTransaction = luna_trade_query()
    ->forOwner($user)
    ->whereHandler('standard')
    ->first();
```

## 扩展构建器

如果需要自定义的构建器，可以继承基类：

```php
use Dybasedev\LunaPrototype\Trade\TradeOperationBuilder;
use Dybasedev\LunaPrototype\Trade\TradeQueryBuilder;

class CustomTradeOperationBuilder extends TradeOperationBuilder
{
    public function updateStatus(int $status): StatusChangeResult
    {
        // 实现自定义逻辑
    }
    
    // 实现其他抽象方法...
}

class CustomTradeQueryBuilder extends TradeQueryBuilder
{
    public function whereCustomField($value): static
    {
        $this->filters['custom_field'] = $value;
        return $this;
    }
    
    public function paginate(): mixed
    {
        // 实现自定义查询逻辑
    }
    
    // 实现其他抽象方法...
}
```

## 支付系统

Trade 组件包含完整的支付系统，支持多种支付方式的集成和管理。

### 配置支付

支付功能由独立的支付组件（如 DnW）提供：

```php
// 在支付组件的 ServiceProvider 中注册支付提供者
use Dybasedev\LunaPrototype\Trade\Payment\PaymentProvider;

$this->app->singleton(PaymentProvider::class, function ($app) {
    return new YourPaymentProvider([
        'default_method' => 'balance',
        // 其他配置...
    ]);
});
```

### 使用支付

```php
// 获取可用支付方式
$methods = $handler->getAvailablePaymentMethods($transaction);

// 执行支付
$result = $handler->processPayment($transaction, 'balance', [
    'password' => '123456'
]);

if ($result->isPaid()) {
    // 支付成功
} elseif ($result->needsRedirect()) {
    // 跳转到第三方支付
    return redirect($result->getRedirectUrl());
}
```

详细文档请参考 [支付系统文档](./Payment/README.md)。

## 注意事项

1. **事务安全**：所有交易操作都在数据库事务中执行，确保数据一致性
2. **状态管理**：严格控制状态转换，防止非法操作
3. **过期处理**：支持自动处理过期交易，需要配置定时任务
4. **扩展性**：通过自定义处理器支持各种业务场景
5. **精度问题**：所有金额计算保留2位小数，避免浮点数精度问题
6. **修饰器模式**：TransactionPreview 使用修饰器模式，更加灵活和可扩展

## 完整示例

```php
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\*;

// 1. 创建可交易对象
$product = luna_trade()->createTradable([
    'code' => 'PROD001',
    'name' => 'iPhone 15',
    'title' => 'iPhone 15 128GB',
    'amount' => 5999.00,
    'origin_amount' => 6499.00,
    'stock' => 100,
    'handler_id' => hash_code('standard'),
    'status' => 1,
]);

// 2. 创建交易上下文（可选）
$context = TransactionContext::make()
    ->fromChannel('mobile_app')
    ->withParameters(['member_discount' => 0.1]);

// 3. 生成预览（可选）
$handler = luna_trade()->getFlowHandler('standard');
$preview = $handler->generatePreview($user, $product, $context);

// 4. 应用优惠
$memberDiscount = new DiscountModifier('member', '会员折扣', 10.0, 'percentage');
$coupon = new CouponModifier('NEW2024', '新年优惠券', 200.0, 'fixed');
$shipping = new ShippingFeeModifier('express', 15.0, 199.0);

$preview->addModifiers([$memberDiscount, $coupon, $shipping]);

// 5. 创建交易
$transaction = $handler->createTransactionFromPreview($preview, [
    'shipping_address' => '北京市朝阳区...',
    'remark' => '请尽快发货',
]);

// 6. 支付交易
$result = $trade->updateTransactionStatus(
    $transaction,
    StandardTransactionStatus::Paid->getCode(),
    [
        'payment_method' => 'wechat',
        'payment_no' => 'WX123456',
    ]
);

if ($result->isSuccess()) {
    // 处理支付成功逻辑
} else {
    // 处理失败情况
    $reason = $result->getReason();
}

// 7. 查询交易历史
$history = luna_trade_query()
    ->forOwner($user)
    ->whereStatus(StandardTransactionStatus::Completed->getCode())
    ->whereDateBetween('2024-01-01', '2024-12-31')
    ->perPage(20)
    ->paginate();

// 或使用操作构建器更新状态
luna_trade_operation()
    ->transaction($transaction)
    ->withContext(['payment_method' => 'alipay'])
    ->updateStatus(StandardTransactionStatus::Paid->getCode());
```

## 附录

### 附录 A: TransactionPreview 使用指南

#### 概述

`TransactionPreview` 类已经重构为使用灵活的修饰器模式来处理金额变化。这种设计允许开发者根据具体业务需求自由组合和扩展功能，而不受框架预设功能的限制。

#### 核心概念

##### 金额修饰器（AmountModifier）

金额修饰器是一个抽象类，用于定义如何修改交易金额。每个修饰器都必须：

1. 实现 `calculate()` 方法来计算修改后的金额
2. 实现 `canApply()` 方法来判断是否可以应用
3. 实现 `getDescription()` 方法来提供可读的描述
4. 可序列化，以便在交易记录中持久化

##### 修饰器类型

系统预定义了以下修饰器类型：
- `discount` - 折扣类
- `fee` - 费用类
- `tax` - 税费类
- `promotion` - 促销类
- `coupon` - 优惠券类
- `custom` - 自定义类

##### 优先级

修饰器按优先级顺序应用（数字越小越先执行）：
- 0-99: 折扣和优惠券
- 100-149: 促销活动
- 150-199: 税费
- 200+: 运费和其他费用

#### 使用场景示例

##### 1. 基础电商场景

```php
use Dybasedev\LunaPrototype\Trade\TransactionPreview;
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\DiscountModifier;
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\ShippingFeeModifier;
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\TaxModifier;

// 创建交易预览
$preview = new TransactionPreview($user);

// 添加商品
$preview->addItem($product1, 2)
        ->addItem($product2, 1);

// 应用会员折扣（9折）
$memberDiscount = new DiscountModifier(
    'member_discount',
    '会员折扣',
    10.0,
    'percentage'
);
$preview->addModifier($memberDiscount);

// 添加运费
$shipping = new ShippingFeeModifier(
    'standard_shipping',
    baseFee: 10.0,
    freeThreshold: 99.0
);
$preview->addModifier($shipping);

// 添加税费（13%增值税）
$tax = new TaxModifier(
    'vat',
    defaultRate: 13.0,
    includedInPrice: false
);
$preview->addModifier($tax);

// 获取最终金额
$finalAmount = $preview->getFinalAmount();
```

##### 2. 优惠券场景

```php
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\CouponModifier;

// 创建优惠券修饰器
$coupon = new CouponModifier(
    'SAVE20',
    '新用户优惠券',
    20.0,
    'fixed'
);

// 设置优惠券规则
$coupon->setMetadata('min_purchase', 50.0)  // 最低消费50元
       ->setMetadata('expires_at', now()->addDays(30))  // 30天有效期
       ->setMetadata('per_user_limit', 1);  // 每用户限用1次

// 应用优惠券
try {
    $preview->addModifier($coupon);
} catch (\InvalidArgumentException $e) {
    // 优惠券不满足使用条件
    echo "优惠券无法使用：" . $e->getMessage();
}
```

##### 3. 复杂促销场景

```php
// 创建限时折扣
$flashSale = new DiscountModifier(
    'flash_sale_2024',
    '限时秒杀',
    30.0,
    'percentage',
    maxAmount: 50.0  // 最高减免50元
);
$flashSale->setMetadata('valid_from', now())
          ->setMetadata('valid_until', now()->addHours(2))
          ->setPriority(10);  // 优先级高，先于其他折扣

// 满减活动
$bulkDiscount = new class('bulk_100', '满100减20', 20.0) extends DiscountModifier {
    public function canApply(TransactionPreview $preview): bool
    {
        return $preview->getBaseAmount() >= 100.0;
    }
};

// 组合应用
$preview->addModifier($flashSale)
        ->addModifier($bulkDiscount);
```

##### 4. 自定义修饰器

```php
use Dybasedev\LunaPrototype\Trade\AmountModifier;

// 首单优惠修饰器
class FirstOrderDiscountModifier extends AmountModifier
{
    private float $discountPercent;
    
    public function __construct(string $id, float $percent)
    {
        parent::__construct($id, '首单优惠', 'discount');
        $this->discountPercent = $percent;
        $this->setPriority(20);  // 在会员折扣后应用
    }
    
    public function calculate(float $amount, TransactionPreview $preview): float
    {
        $discount = $amount * ($this->discountPercent / 100);
        return $amount - $discount;
    }
    
    public function canApply(TransactionPreview $preview): bool
    {
        $user = $preview->getOwner();
        
        // 检查是否是首单（需要自行实现查询逻辑）
        return $this->isFirstOrder($user);
    }
    
    public function getDescription(): string
    {
        return sprintf('首单%.0f%%优惠', $this->discountPercent);
    }
    
    public static function fromArray(array $data): static
    {
        return new static($data['id'], $data['discount_percent']);
    }
    
    private function isFirstOrder($user): bool
    {
        // 实际业务逻辑
        return true;
    }
}

// 使用自定义修饰器
$firstOrderDiscount = new FirstOrderDiscountModifier('first_order', 15.0);
$preview->addModifier($firstOrderDiscount);
```

##### 5. 动态费用计算

```php
// 按距离计算的配送费
class DistanceBasedShippingModifier extends AmountModifier
{
    private float $baseRate = 5.0;
    private float $perKmRate = 2.0;
    
    public function __construct()
    {
        parent::__construct('distance_shipping', '配送费', 'fee');
        $this->setPriority(200);
    }
    
    public function calculate(float $amount, TransactionPreview $preview): float
    {
        $distance = $this->calculateDistance($preview);
        $shippingFee = $this->baseRate + ($distance * $this->perKmRate);
        
        return $amount + $shippingFee;
    }
    
    private function calculateDistance(TransactionPreview $preview): float
    {
        $address = $preview->getMetadata()['shipping_address'] ?? null;
        if (!$address) {
            return 0;
        }
        
        // 计算距离的业务逻辑
        return 10.5;  // 示例返回10.5公里
    }
    
    // ... 其他必需方法
}
```

#### 获取详细信息

##### 查看修饰器明细

```php
// 获取所有修饰器
$modifiers = $preview->getModifiers();

// 获取特定类型的修饰器
$discounts = $preview->getModifiersByType('discount');
$fees = $preview->getModifiersByType('fee');

// 获取修饰器影响明细
$breakdown = $preview->getModifierBreakdown();
foreach ($breakdown as $item) {
    echo sprintf(
        "%s: %.2f -> %.2f (变化: %.2f)\n",
        $item['modifier']['name'],
        $item['amount_before'],
        $item['amount_after'],
        $item['amount_change']
    );
}
```

##### 导出预览数据

```php
// 转换为数组（包含完整信息）
$data = $preview->toArray();

// 数据结构：
// [
//     'owner' => [...],
//     'items' => [...],
//     'origin_amount' => 100.00,      // 原价总和
//     'base_amount' => 90.00,         // 实际价格总和
//     'final_amount' => 85.50,        // 应用修饰器后的最终金额
//     'total_modifier_amount' => -4.50, // 修饰器产生的总变化
//     'modifiers' => [...],           // 所有修饰器列表
//     'modifier_breakdown' => [...],  // 修饰器影响明细
//     ...
// ]
```

#### 最佳实践

##### 1. 修饰器设计原则

- **单一职责**：每个修饰器只负责一种金额变化
- **可组合性**：修饰器之间应该能够灵活组合
- **透明性**：提供清晰的描述和计算明细
- **可配置性**：通过元数据支持灵活配置

##### 2. 优先级管理

```php
// 推荐的优先级分配
// 10-30: 限时活动、特殊优惠
// 40-60: 会员折扣、等级优惠
// 70-90: 优惠券
// 100-120: 满减、满赠
// 150-170: 税费
// 200+: 运费、手续费等附加费用
```

##### 3. 错误处理

```php
// 处理修饰器应用失败
try {
    $preview->addModifier($modifier);
} catch (\InvalidArgumentException $e) {
    // 记录日志或提示用户
    logger()->warning('修饰器应用失败', [
        'modifier' => $modifier->getId(),
        'reason' => $e->getMessage()
    ]);
}

// 检查修饰器是否可用
if ($modifier->canApply($preview)) {
    $preview->addModifier($modifier);
}
```

##### 4. 性能考虑

```php
// 批量添加修饰器
$modifiers = [
    $discount,
    $coupon,
    $shipping,
    $tax
];

// 过滤可用的修饰器
$applicableModifiers = array_filter($modifiers, function($m) use ($preview) {
    return $m->canApply($preview);
});

// 一次性添加
$preview->addModifiers($applicableModifiers);
```

#### 总结

新的 `TransactionPreview` 设计提供了极大的灵活性，允许开发者：

1. 根据业务需求自定义金额计算逻辑
2. 灵活组合不同的修饰器
3. 轻松扩展新的业务场景
4. 保持代码的清晰和可维护性

通过使用修饰器模式，你可以构建出适合各种复杂业务场景的交易预览系统，而不受框架预设功能的限制。

### 附录 B: 支付提供者集成指南

#### 概述

Trade 组件的支付系统设计为抽象接口，允许第三方组件（如 DnW 入金出金组件）通过实现 `PaymentProvider` 接口来提供具体的支付功能。

#### 支付提供者接口

```php
interface PaymentProvider
{
    public function register(PaymentMethod $paymentMethod): void;
    public function unregister(string $name): void;
    public function get(string $name): ?PaymentMethod;
    public function has(string $name): bool;
    public function all(): array;
    public function getAvailable(SessionHolder $owner, ?TransactionContext $context = null): array;
    public function getDefaultName(): ?string;
    public function setDefaultName(?string $name): void;
    public function setPriority(string $name, int $priority): void;
    public function getSorted(): array;
    public function getConfiguration(): array;
}
```

#### 集成示例：DnW 组件

假设 DnW 组件实现了一个 `DnWPaymentProvider`：

```php
namespace Dybasedev\LunaPrototype\DnW;

use Dybasedev\LunaPrototype\Trade\Payment\PaymentProvider;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentMethod;

class DnWPaymentProvider implements PaymentProvider
{
    private array $methods = [];
    private array $config;
    private ?string $defaultMethod = null;
    private array $priorities = [];
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
        
        // 注册内置的支付方式
        $this->registerBuiltinMethods();
    }
    
    protected function registerBuiltinMethods(): void
    {
        // 注册余额支付
        $this->register(new BalancePaymentMethod([
            'name' => 'balance',
            'display_name' => '账户余额',
            'account_type' => 'main',
        ]));
        
        // 注册银行卡支付
        $this->register(new BankCardPaymentMethod([
            'name' => 'bank_card',
            'display_name' => '银行卡',
        ]));
        
        // 注册其他支付方式...
    }
    
    // 实现 PaymentProvider 接口的所有方法...
}
```

#### 在应用中集成

##### 1. 在 DnW 组件的 ServiceProvider 中注册

```php
namespace Dybasedev\LunaPrototype\DnW;

use Illuminate\Support\ServiceProvider;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentProvider;

class DnWServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 注册支付提供者到容器
        $this->app->singleton(PaymentProvider::class, function ($app) {
            return new DnWPaymentProvider([
                'test_mode' => config('dnw.payment.test_mode', false),
                'default_method' => config('dnw.payment.default_method', 'balance'),
            ]);
        });
    }
}
```

##### 2. Trade 组件自动使用支付提供者

一旦在容器中注册了 `PaymentProvider` 接口的实现：

1. TradeFlowHandler 会自动从容器获取支付提供者
2. 支付相关的方法会自动可用
3. 无需在 Trade 组件中做任何配置

```php
// 在 TradeFlowHandler 中
$handler = luna_trade()->getFlowHandler('standard');

// 获取可用的支付方式
$methods = $handler->getAvailablePaymentMethods($transaction);

// 处理支付
$result = $handler->processPayment($transaction, 'balance', [
    'password' => '123456'
]);
```

#### DnW 组件实现示例

DnW 组件可以提供更丰富的支付功能：

```php
namespace Dybasedev\LunaPrototype\DnW\Payment;

use Dybasedev\LunaPrototype\Trade\Payment\AbstractPaymentMethod;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentResult;

class BalancePaymentMethod extends AbstractPaymentMethod
{
    public function pay(
        Transaction $transaction,
        array $parameters = [],
        ?TransactionContext $context = null
    ): PaymentResult {
        // 获取用户账户
        $account = luna_dnw_account()
            ->getAccount($transaction->getOwner(), $this->config->get('account_type'));
            
        // 检查余额
        if ($account->available_balance < $transaction->getAmount()) {
            return PaymentResult::failure('余额不足');
        }
        
        // 验证支付密码
        if ($this->config->get('require_password', true)) {
            if (!$this->verifyPassword($transaction->getOwner(), $parameters['password'] ?? '')) {
                return PaymentResult::failure('支付密码错误');
            }
        }
        
        // 执行扣款
        $result = luna_dnw_withdraw()
            ->from($account)
            ->amount($transaction->getAmount())
            ->reason('trade_payment')
            ->reference($transaction->getTransactionNumber())
            ->execute();
            
        if ($result->isSuccess()) {
            return PaymentResult::success([
                'payment_no' => $result->getTransactionCode(),
                'transaction_no' => $transaction->getTransactionNumber(),
                'paid_amount' => $transaction->getAmount(),
                'paid_at' => now(),
            ]);
        }
        
        return PaymentResult::failure($result->getMessage());
    }
    
    public function refund(
        Transaction $transaction,
        float $amount,
        string $reason,
        array $parameters = []
    ): PaymentResult {
        // 执行退款到账户
        $result = luna_dnw_deposit()
            ->to($transaction->getOwner(), $this->config->get('account_type'))
            ->amount($amount)
            ->reason('trade_refund')
            ->reference($transaction->getTransactionNumber())
            ->execute();
            
        if ($result->isSuccess()) {
            return PaymentResult::refunded([
                'payment_no' => $result->getTransactionCode(),
                'refund_amount' => $amount,
                'refunded_at' => now(),
            ]);
        }
        
        return PaymentResult::failure($result->getMessage());
    }
}
```

#### 最佳实践

1. **解耦设计**：Trade 组件不依赖具体的支付实现，通过接口定义行为
2. **依赖注入**：使用 Laravel 容器管理支付提供者的注入
3. **配置驱动**：通过配置类管理支付提供者的注册和配置
4. **扩展性**：新的支付组件只需实现接口即可集成

#### 总结

这种设计让 Trade 组件保持简洁和专注于交易流程管理，而具体的支付实现由专门的组件（如 DnW）提供。这样的架构具有良好的扩展性和可维护性。

### 附录 C: TradableHandler 使用指南

#### 概述

`TradableHandler` 是一个抽象类，为可交易对象提供处理器支持。它负责价格计算、格式化显示、验证和交易生命周期回调等功能。

#### 管理机制

`TradableHandler` 的管理和获取是通过 `TradeFlowHandler` 来完成的，而不是全局注册。每个 `TradeFlowHandler` 可以返回对应的 `TradableHandler` 实例。

```php
// 在 TradeFlowHandler 中重写 getTradableHandler 方法
protected function getTradableHandler(Tradable $tradable): ?TradableHandler
{
    // 根据 tradable 类型返回对应的处理器
    if ($tradable instanceof Product) {
        return app(LunaHandler::class)->createHandlerInstance('product-handler');
    }
    
    // 返回默认处理器或 null
    return parent::getTradableHandler($tradable);
}
```

#### 核心概念

##### 价格计算

`calculatePrice()` 方法负责计算可交易对象的价格。标准实现仅返回基础价格，不包含任何业务逻辑（如折扣）。

如需折扣、优惠券等功能，请使用 `TransactionPreview` 的 `AmountModifier` 机制。

##### 生命周期回调

处理器提供了交易各个阶段的回调方法：
- `beforeTransactionCreate()` - 交易创建前
- `afterTransactionCreated()` - 交易创建后
- `afterTransactionPaid()` - 交易支付后
- `afterTransactionCompleted()` - 交易完成后
- `afterTransactionCanceled()` - 交易取消后
- `afterTransactionRefunded()` - 交易退款后

#### 实现示例

##### 1. 简单商品处理器

```php
use Dybasedev\LunaPrototype\Trade\TradableHandler;
use Dybasedev\LunaPrototype\Trade\Tradable;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionContext;

class SimpleProductHandler extends TradableHandler
{
    public function handlerName(): string
    {
        return 'simple-product';
    }
    
    public function handlerDescription(): string
    {
        return '简单商品处理器';
    }
    
    public function calculatePrice(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        $unitPrice = $tradable->getTradablePrice();
        $originUnitPrice = $tradable->getTradableOriginPrice();
        
        return [
            'price' => round($unitPrice * $quantity, 2),
            'origin_price' => round($originUnitPrice * $quantity, 2),
            'unit_id' => $tradable->getTradablePriceUnit(),
            'metadata' => [
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
            ],
        ];
    }
    
    public function formatPrice(
        float $price,
        string|int|null $unitId = null,
        array $options = []
    ): string {
        return '¥' . number_format($price, 2);
    }
    
    public function formatForDisplay(Tradable $tradable, array $options = []): array
    {
        return [
            'id' => $tradable->getTradableId(),
            'name' => $tradable->getTradableName(),
            'price' => $this->formatPrice($tradable->getTradablePrice()),
            'available' => $tradable->isTradableAvailable(),
        ];
    }
}
```

##### 2. 库存管理处理器

```php
class InventoryProductHandler extends TradableHandler
{
    public function handlerName(): string
    {
        return 'inventory-product';
    }
    
    public function handlerDescription(): string
    {
        return '带库存管理的商品处理器';
    }
    
    public function beforeTransactionCreate(
        Tradable $tradable,
        float $quantity,
        ?TransactionContext $context = null
    ): void {
        // 预留库存
        $this->reserveStock($tradable, $quantity);
    }
    
    public function afterTransactionPaid(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $paymentInfo = []
    ): void {
        // 确认扣减库存
        $this->confirmStockDeduction($tradable, $quantity);
        
        // 记录销售
        $this->recordSale($tradable, $quantity, $transaction);
    }
    
    public function afterTransactionCanceled(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        string $reason
    ): void {
        // 恢复库存
        $this->restoreStock($tradable, $quantity);
        
        // 记录取消原因
        logger()->info('Transaction canceled', [
            'tradable_id' => $tradable->getTradableId(),
            'quantity' => $quantity,
            'reason' => $reason,
        ]);
    }
    
    private function reserveStock(Tradable $tradable, float $quantity): void
    {
        // 实现库存预留逻辑
        // 例如：更新数据库中的 reserved_stock 字段
    }
    
    private function confirmStockDeduction(Tradable $tradable, float $quantity): void
    {
        // 实现库存扣减逻辑
        // 例如：减少 stock，清除 reserved_stock
    }
    
    private function restoreStock(Tradable $tradable, float $quantity): void
    {
        // 实现库存恢复逻辑
    }
    
    private function recordSale(Tradable $tradable, float $quantity, Transaction $transaction): void
    {
        // 记录销售数据
    }
    
    // ... 其他必需方法
}
```

##### 3. 数字商品处理器

```php
class DigitalProductHandler extends TradableHandler
{
    public function handlerName(): string
    {
        return 'digital-product';
    }
    
    public function handlerDescription(): string
    {
        return '数字商品处理器';
    }
    
    public function validate(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        // 数字商品特殊验证
        if ($quantity != floor($quantity)) {
            return [
                'valid' => false,
                'message' => '数字商品只能购买整数个',
                'code' => 'INVALID_QUANTITY',
            ];
        }
        
        // 调用父类基础验证
        return parent::validate($tradable, $quantity, $context);
    }
    
    public function afterTransactionPaid(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $paymentInfo = []
    ): void {
        // 生成激活码或授权
        $licenses = $this->generateLicenses($tradable, $quantity);
        
        // 发送给用户
        $this->deliverLicenses($transaction, $licenses);
        
        // 记录发放历史
        $this->recordDelivery($transaction, $licenses);
    }
    
    public function supportsPaymentMethod(
        Tradable $tradable,
        string $paymentMethod,
        ?TransactionContext $context = null
    ): bool {
        // 数字商品不支持货到付款
        if ($paymentMethod === 'cash_on_delivery') {
            return false;
        }
        
        return true;
    }
    
    private function generateLicenses(Tradable $tradable, float $quantity): array
    {
        // 生成授权码逻辑
        $licenses = [];
        for ($i = 0; $i < $quantity; $i++) {
            $licenses[] = $this->generateLicenseKey();
        }
        return $licenses;
    }
    
    private function generateLicenseKey(): string
    {
        // 生成唯一的授权码
        return strtoupper(bin2hex(random_bytes(16)));
    }
    
    private function deliverLicenses(Transaction $transaction, array $licenses): void
    {
        // 实现发送授权码给用户的逻辑
        // 例如：发送邮件、站内信等
    }
    
    private function recordDelivery(Transaction $transaction, array $licenses): void
    {
        // 记录发放历史
        foreach ($licenses as $license) {
            // 保存到数据库
        }
    }
    
    // ... 其他必需方法
}
```

##### 4. 服务类商品处理器

```php
class ServiceProductHandler extends TradableHandler
{
    public function handlerName(): string
    {
        return 'service-product';
    }
    
    public function handlerDescription(): string
    {
        return '服务类商品处理器';
    }
    
    public function calculatePrice(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        // 服务可能按时长计费
        $duration = $context?->getParameter('duration') ?? 1; // 默认1小时
        $hourlyRate = $tradable->getTradablePrice();
        
        $totalPrice = $hourlyRate * $duration * $quantity;
        
        return [
            'price' => round($totalPrice, 2),
            'origin_price' => round($totalPrice, 2),
            'unit_id' => 'hour',
            'metadata' => [
                'hourly_rate' => $hourlyRate,
                'duration' => $duration,
                'quantity' => $quantity,
            ],
        ];
    }
    
    public function formatForDisplay(Tradable $tradable, array $options = []): array
    {
        $info = parent::formatForDisplay($tradable, $options);
        
        // 添加服务特有信息
        $info['service_info'] = [
            'duration_unit' => '小时',
            'min_duration' => 1,
            'max_duration' => 24,
            'available_slots' => $this->getAvailableSlots($tradable),
        ];
        
        return $info;
    }
    
    public function afterTransactionPaid(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $paymentInfo = []
    ): void {
        // 创建服务预约
        $this->createServiceAppointment($transaction, $tradable);
        
        // 发送确认通知
        $this->sendConfirmation($transaction);
    }
    
    private function getAvailableSlots(Tradable $tradable): array
    {
        // 获取可用时间段
        return [
            '09:00-12:00',
            '14:00-17:00',
            '19:00-21:00',
        ];
    }
    
    private function createServiceAppointment(Transaction $transaction, Tradable $tradable): void
    {
        // 创建服务预约记录
    }
    
    private function sendConfirmation(Transaction $transaction): void
    {
        // 发送服务预约确认
    }
    
    // ... 其他必需方法
}
```

#### 与其他组件集成

##### 使用 AmountModifier 实现复杂定价

虽然 `TradableHandler` 不直接处理折扣，但可以与 `TransactionPreview` 的修饰器配合使用：

```php
// 1. Handler 返回基础价格
$handler = new SimpleProductHandler();
$priceInfo = $handler->calculatePrice($product, 2);

// 2. 在 TransactionPreview 中应用折扣
$preview = new TransactionPreview($user);
$preview->addItem($product, 2);

// 添加会员折扣
if ($user->isMember()) {
    $memberDiscount = new MemberDiscountModifier($user->getMemberLevel());
    $preview->addModifier($memberDiscount);
}

// 添加批量购买折扣
if ($quantity >= 10) {
    $bulkDiscount = new BulkPurchaseDiscountModifier($quantity);
    $preview->addModifier($bulkDiscount);
}
```

##### 与库存系统集成

```php
class InventoryAwareHandler extends TradableHandler
{
    private InventoryService $inventory;
    
    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }
    
    public function validate(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        // 使用外部库存服务验证
        if (!$this->inventory->checkAvailability($tradable->getTradableId(), $quantity)) {
            return [
                'valid' => false,
                'message' => '库存不足',
                'code' => 'OUT_OF_STOCK',
            ];
        }
        
        return parent::validate($tradable, $quantity, $context);
    }
    
    public function afterTransactionPaid(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $paymentInfo = []
    ): void {
        // 通知库存系统扣减库存
        $this->inventory->deduct($tradable->getTradableId(), $quantity, [
            'transaction_id' => $transaction->getTransactionId(),
            'reason' => 'sale',
        ]);
    }
}
```

#### 最佳实践

1. **保持抽象**：Handler 应该专注于处理逻辑，不要包含具体的业务规则
2. **使用上下文**：通过 `TransactionContext` 传递额外信息，保持方法签名简洁
3. **错误处理**：在回调方法中妥善处理异常，避免影响交易流程
4. **日志记录**：在关键操作点记录日志，便于追踪和调试
5. **性能考虑**：避免在 `calculatePrice` 等频繁调用的方法中执行耗时操作

#### 总结

`TradableHandler` 提供了一个清晰的抽象层，让开发者可以：

1. 自定义价格计算逻辑（但不包含折扣等业务逻辑）
2. 在交易的各个阶段执行自定义操作
3. 实现特定类型商品的验证规则
4. 与外部系统（如库存、物流）集成

通过将具体的业务逻辑（如折扣）移到 `AmountModifier`，我们保持了组件的原子性和可组合性，符合 Luna 框架的设计理念。