# 标准交易流程

本目录包含 Trade 组件的标准实现，提供了一套开箱即用的交易处理方案。

## 核心组件

### StandardTradeFlowHandler
标准交易流程处理器，实现了完整的电商交易流程，适用于大多数常见的在线交易场景。

### StandardTradableHandler
标准可交易对象处理器，提供价格计算、格式化、验证等功能的标准实现。

### StandardTransactionStatusManager
标准交易状态管理器，管理所有状态对象的创建和状态转换验证。

### 状态对象
每个交易状态都是一个独立的类，继承自 `TransactionStatus`：
- `PendingPaymentStatus` - 待支付状态
- `PaidStatus` - 已支付状态
- `CompletedStatus` - 已完成状态
- `CanceledStatus` - 已取消状态
- `ExpiredStatus` - 已过期状态

## 状态对象架构

每个状态都是一个独立的对象，可以处理状态进入和离开的业务逻辑：

```php
// 状态对象示例
class PaidStatus extends TransactionStatus
{
    protected string $key = 'paid';
    protected string $name = '已支付';
    
    public function onReached(
        TradeTransaction $transaction,
        ?TransactionStatus $fromStatus,
        array $context = []
    ): void {
        // 记录支付信息
        // 扣减库存
        // 发送通知等
    }
    
    public function getAllowedTransitions(): array
    {
        return ['shipped', 'canceled', 'refunded'];
    }
}
```

### 核心状态

标准交易流程只包含最基础的状态，保持简洁：

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

### 状态转换规则

```
pending_payment -> paid / canceled / expired
paid -> completed / canceled
```

- 最终状态：completed, canceled, expired
- 初始状态：pending_payment

### 为什么只有这些状态？

标准流程故意保持简洁，只包含所有交易系统都需要的核心状态。这样做的好处是：

1. **通用性强**：适用于各种类型的交易（实物、虚拟商品、服务等）
2. **易于理解**：状态少，流程清晰
3. **灵活扩展**：业务方可以根据需要添加自己的状态

如果您的业务需要更多状态（如发货、收货、退款等），请参考 [扩展状态指南](./ExtendStatus.md)。

### 状态对象的优势

1. **封装业务逻辑**：每个状态的进入/离开逻辑都封装在对应的状态类中
2. **易于扩展**：添加新状态只需创建新的状态类
3. **状态隔离**：每个状态的行为独立，不会相互影响
4. **自动化处理**：状态转换时自动触发相应的业务逻辑（如扣减库存、发送通知等）

## 交易流程

### 1. 生成交易预览

```php
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeFlowHandler;
use Dybasedev\LunaPrototype\Trade\TransactionContext;

$handler = new StandardTradeFlowHandler();

// 创建交易上下文
$context = TransactionContext::make()
    ->fromChannel('web')
    ->fromCampaign('summer_sale_2024')
    ->withParameters([
        'quantity' => 2,
        'expire_minutes' => 30,
    ]);

// 生成预览
$preview = $handler->generatePreview($user, $product, $context);

// 预览包含的信息
$previewData = $preview->toArray();
// [
//     'items' => [...],
//     'amount' => 1999.00,
//     'origin_amount' => 2399.00,
//     'discounts' => [...],
//     'available_payment_methods' => ['alipay', 'wechat', 'balance'],
//     ...
// ]
```

### 2. 确认并创建交易

```php
// 用户确认交易
$confirmation = [
    'shipping_address' => [
        'name' => '张三',
        'phone' => '13800138000',
        'address' => '北京市朝阳区...',
    ],
    'payment_method' => 'alipay',
    'remark' => '请尽快发货',
];

// 创建交易
$transaction = $handler->createTransactionFromPreview($preview, $confirmation);
```

### 3. 处理支付

```php
// 支付成功后更新状态
$handler->handleStatusChange(
    $transaction,
    StandardTransactionStatus::PENDING_PAYMENT->getCode(),
    StandardTransactionStatus::PAID->getCode(),
    [
        'payment_method' => 'alipay',
        'payment_no' => 'ALI202401010001',
    ]
);
```

### 4. 发货

```php
// 商家发货
$handler->handleStatusChange(
    $transaction,
    StandardTransactionStatus::PAID->getCode(),
    StandardTransactionStatus::SHIPPED->getCode(),
    [
        'tracking_no' => 'SF1234567890',
        'shipping_company' => '顺丰速运',
    ]
);
```

### 5. 确认收货

```php
// 买家确认收货
$handler->handleStatusChange(
    $transaction,
    StandardTransactionStatus::SHIPPED->getCode(),
    StandardTransactionStatus::RECEIVED->getCode(),
    []
);
```

### 6. 完成交易

```php
// 完成交易
$handler->completeTransaction($transaction);
```

## 特殊处理

### 取消交易

```php
// 取消未支付的交易
$handler->cancelTransaction($transaction, '买家取消');

// 取消已支付的交易（会触发退款）
$handler->cancelTransaction($transaction, '商家无货');
```

### 处理过期

```php
// 自动处理过期交易
$handler->handleExpiredTransaction($transaction);
```

## 扩展标准流程

### 自定义状态处理

```php
class CustomStandardHandler extends StandardTradeFlowHandler
{
    protected function handlePaymentCompleted(
        TradeTransaction $transaction, 
        array $context
    ): void {
        parent::handlePaymentCompleted($transaction, $context);
        
        // 添加自定义逻辑
        // 例如：发送订单确认邮件
        $this->sendOrderConfirmationEmail($transaction);
        
        // 例如：扣减实际库存
        $this->deductPhysicalStock($transaction);
    }
    
    protected function getAvailablePaymentMethods(
        TransactionPreview $preview
    ): array {
        // 根据业务逻辑定制支付方式
        $methods = ['alipay', 'wechat'];
        
        // 金额大于1000才支持信用卡
        if ($preview->getAmount() > 1000) {
            $methods[] = 'credit_card';
        }
        
        return $methods;
    }
}
```

### 集成其他组件

```php
// 与 AssetsAccount 集成
protected function handlePaymentCompleted(
    TradeTransaction $transaction, 
    array $context
): void {
    parent::handlePaymentCompleted($transaction, $context);
    
    // 使用余额支付时扣减账户余额
    if ($context['payment_method'] === 'balance') {
        luna_account_update()
            ->account($transaction->getOwner(), 'balance')
            ->available()
            ->event('trade_payment')
            ->decrease($transaction->amount)
            ->submit();
    }
}

// 与 UnitConversion 集成
public function generatePreview(
    SessionHolder $owner,
    Tradable|array $tradables,
    ?TransactionContext $context = null
): TransactionPreview {
    $preview = parent::generatePreview($owner, $tradables, $context);
    
    // 支持多币种
    if ($currency = $context?->getParameter('currency')) {
        $rate = luna_unit_conversion()?->getConversionRate('CNY', $currency);
        if ($rate) {
            $preview->setMetadata('currency', $currency);
            $preview->setMetadata('exchange_rate', $rate);
            // 转换金额显示
        }
    }
    
    return $preview;
}
```

## 配置使用

```php
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeFlowHandler;

$configure = LunaTradeConfigure::create()
    ->registerFlowHandler('standard', StandardTradeFlowHandler::class)
    ->build();

// 创建交易时使用
$transaction = luna_trade()->createTransaction(
    $user,
    'standard',  // 使用标准流程
    $product,
    ['quantity' => 2]
);
```

## StandardTradableHandler 使用示例

### 基本使用

```php
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradableHandler;

$handler = new StandardTradableHandler();

// 计算价格
$priceInfo = $handler->calculatePrice($product, 2.0);
// [
//     'unit_price' => 999.00,
//     'origin_unit_price' => 1299.00,
//     'amount' => 1998.00,
//     'origin_amount' => 2598.00,
//     'discount_rate' => 0.2308,
// ]

// 格式化价格
$formatted = $handler->formatPrice(999.00);
// "¥999.00"

// 验证可交易对象
$validation = $handler->validateTradable($product, 10.0);
// [
//     'valid' => true/false,
//     'errors' => [...],
// ]

// 获取展示信息
$displayInfo = $handler->getDisplayInfo($product);
// [
//     'id' => 1,
//     'name' => 'iPhone 15',
//     'price' => 999.00,
//     'price_formatted' => '¥999.00',
//     'discount' => [...],
//     ...
// ]
```

### 与上下文集成

```php
// 创建带会员折扣的上下文
$context = TransactionContext::make()
    ->withParameters([
        'member_discount' => 0.1,  // 会员9折
        'max_quantity' => 5,       // 限购5件
    ])
    ->fromCampaign('vip_sale', [
        'discount' => 0.05,        // 活动额外95折
    ]);

// 计算会员价格
$memberPrice = $handler->calculatePrice($product, 2.0, $context);
// 价格会自动应用会员折扣和活动折扣

// 验证购买限制
$validation = $handler->validateTradable($product, 10.0, $context);
// ['valid' => false, 'errors' => ['超过最大购买数量限制（5）']]
```

### 扩展 StandardTradableHandler

```php
class MyTradableHandler extends StandardTradableHandler
{
    public function calculatePrice(
        Tradable $tradable, 
        float $quantity = 1.0, 
        ?TransactionContext $context = null
    ): array {
        $result = parent::calculatePrice($tradable, $quantity, $context);
        
        // 批量折扣
        if ($quantity >= 10) {
            $result['unit_price'] *= 0.9;  // 10件以上9折
            $result['amount'] = $result['unit_price'] * $quantity;
        }
        
        return $result;
    }
    
    public function afterTransactionPaid(
        Tradable $tradable, 
        TradeTransaction $transaction, 
        float $quantity, 
        array $paymentInfo = []
    ): void {
        parent::afterTransactionPaid($tradable, $transaction, $quantity, $paymentInfo);
        
        // 扣减库存
        $this->deductStock($tradable, $quantity);
        
        // 增加销售统计
        $this->updateSalesStatistics($tradable, $quantity, $transaction->amount);
        
        // 赠送积分
        if ($buyer = $transaction->getOwner()) {
            $points = floor($transaction->amount * 0.1);  // 10%积分返还
            $this->grantPoints($buyer, $points);
        }
    }
}
```

## 注意事项

1. **库存管理**：标准实现中的 `deductStock()` 和 `restoreStock()` 方法需要根据实际业务实现
2. **支付集成**：需要集成实际的支付系统来处理支付和退款
3. **通知机制**：建议在状态变更时添加通知逻辑（邮件、短信、站内信等）
4. **日志记录**：重要操作应记录日志以便追踪
5. **并发控制**：高并发场景下需要考虑库存锁定等机制
6. **价格精度**：所有价格计算都会保留2位小数，避免精度问题
7. **处理器复用**：TradableHandler 是无状态的，可以作为单例使用