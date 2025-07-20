# 交易支付系统

## 概述

Trade 组件的支付系统提供了灵活的支付方式管理框架，支持多种支付方式的集成和管理。系统采用抽象化设计，可以轻松扩展新的支付方式。

## 核心概念

### 配置管理

Trade 支付系统使用 `Foundation\Configuration\Repository` 统一管理配置，确保配置处理的一致性。

```php
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentMethodConfigurationRepository;

// 支付方式配置使用专门的配置仓库
$config = new PaymentMethodConfigurationRepository([
    'name' => 'alipay',
    'display_name' => '支付宝',
    'api_key' => 'your_api_key',
    'private_key' => 'your_private_key',
]);

// 自动隐藏敏感信息
$config->hideSensitiveData();

// 便捷的配置访问方法
echo $config->getDisplayName();        // '支付宝'
echo $config->getMinAmount();          // 0.01
echo $config->requiresPassword();      // false
```

配置可以来自多种来源：
- 配置文件
- 数据库模型的 config JSON 字段
- 外部传入的数组

## 核心概念

### 1. PaymentMethod（支付方式）

定义了支付方式的基本行为，所有支付方式都需要实现此接口。

```php
interface PaymentMethod
{
    public function getName(): string;
    public function getDisplayName(): string;
    public function isAvailable(SessionHolder $owner, ?TransactionContext $context = null): bool;
    public function pay(Transaction $transaction, array $parameters = [], ?TransactionContext $context = null): PaymentResult;
    public function refund(Transaction $transaction, float $amount, string $reason, array $parameters = []): PaymentResult;
    // ...
}
```

### 2. PaymentProvider（支付提供者）

管理和提供支付方式，负责支付方式的注册、获取和管理。

```php
interface PaymentProvider
{
    public function register(PaymentMethod $paymentMethod): void;
    public function get(string $name): ?PaymentMethod;
    public function getAvailable(SessionHolder $owner, ?TransactionContext $context = null): array;
    // ...
}
```

### 3. PaymentResult（支付结果）

封装支付操作的结果，包括成功状态、错误信息、支付单号等。

```php
// 成功支付
$result = PaymentResult::success([
    'payment_no' => 'PAY123456',
    'paid_amount' => 999.00,
    'paid_at' => now(),
]);

// 待处理（需要跳转）
$result = PaymentResult::pending('https://payment.gateway.com/pay/123456');

// 失败
$result = PaymentResult::failure('Insufficient balance');
```

## 快速开始

### 1. 配置支付系统

```php
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentConfiguration;

$configure = LunaTradeConfigure::create()
    ->configurePayment(function (PaymentConfiguration $payment) {
        // 使用标准支付提供者
        $payment->useStandardProvider();
        
        // 注册资产账户支付方式
        $payment->registerAssetsAccountMethod([
            'name' => 'balance',
            'display_name' => '余额支付',
            'account_type' => 'balance',
            'discount_rate' => 5,  // 5% 优惠
            'require_password' => true,
        ]);
        
        // 注册其他支付方式
        $payment->registerMethod('alipay', AlipayPaymentMethod::class, [
            'app_id' => 'your_app_id',
            'private_key' => 'your_private_key',
        ]);
        
        // 设置默认支付方式
        $payment->setDefaultMethod('balance');
        
        // 设置优先级
        $payment->setPriorities([
            'balance' => 100,
            'alipay' => 90,
            'wechat' => 80,
        ]);
    })
    ->build();
```

### 2. 在交易预览中应用支付方式

```php
// 生成交易预览时考虑支付方式的影响
$context = TransactionContext::make()
    ->withParameters(['payment_method' => 'balance']);

$preview = $handler->generatePreview($user, $product, $context);

// 预览会自动应用支付方式的优惠（如果有）
echo $preview->getFinalAmount(); // 已应用 5% 优惠
```

### 3. 处理支付

```php
// 创建交易
$transaction = $handler->createTransactionFromPreview($preview);

// 执行支付
$paymentResult = $handler->processPayment(
    $transaction,
    'balance',  // 支付方式
    [
        'password' => '123456',  // 支付密码
    ]
);

if ($paymentResult->isPaid()) {
    // 支付成功，更新交易状态
    $trade->updateTransactionStatus(
        $transaction,
        StandardTransactionStatus::Paid->getCode(),
        ['payment_result' => $paymentResult->toArray()]
    );
} elseif ($paymentResult->needsRedirect()) {
    // 需要跳转到第三方支付页面
    return redirect($paymentResult->getRedirectUrl());
} else {
    // 支付失败
    echo $paymentResult->getReason();
}
```

### 4. 处理支付回调

```php
// 第三方支付回调处理
Route::post('/payment/callback/{method}', function ($method, Request $request) {
    $handler = luna_trade()->getFlowHandler('standard');
    
    $result = $handler->handlePaymentCallback(
        $method,
        $request->all()
    );
    
    if ($result->isPaid()) {
        // 更新交易状态
        $transaction = luna_trade()->getTransactionByNumber($result->getTransactionNo());
        luna_trade()->updateTransactionStatus(
            $transaction,
            StandardTransactionStatus::Paid->getCode()
        );
    }
});
```

### 5. 处理退款

```php
// 发起退款
$refundResult = $handler->processRefund(
    $transaction,
    'balance',  // 原支付方式
    500.00,     // 退款金额
    '用户申请退款',
    ['operator' => 'admin']
);

if ($refundResult->isSuccess()) {
    echo "退款成功，退款单号：" . $refundResult->getPaymentNo();
}
```

## 使用 AssetsAccount 支付适配器

资产账户支付适配器集成了 AssetsAccount 组件，使用账户余额进行支付。

### 配置示例

```php
$payment->registerAssetsAccountMethod([
    'name' => 'balance',
    'display_name' => '余额支付',
    'description' => '使用账户余额进行支付',
    'icon' => 'icon-wallet',
    'account_type' => 'balance',  // 账户类型
    'discount_rate' => 5,         // 优惠比例
    'require_password' => true,   // 是否需要支付密码
    'event_name' => 'trade_payment',       // 支付事件名
    'refund_event_name' => 'trade_refund', // 退款事件名
    'password_verifier' => function ($owner, $password) {
        // 自定义密码验证逻辑
        return $owner->verifyPaymentPassword($password);
    },
]);
```

### 使用示例

```php
// 检查余额是否可用
$availability = $paymentMethod->getAvailability($user);
if (!$availability['available']) {
    echo "余额支付不可用：" . $availability['reason'];
    return;
}

echo "可用余额：" . $availability['metadata']['available_balance'];

// 执行支付
$result = $paymentMethod->pay($transaction, [
    'password' => '123456',
    'remark' => '购买商品',
]);

if ($result->isPaid()) {
    // 支付成功，余额已扣除
    $balanceTransactionCode = $result->getExtraData()['balance_transaction_code'];
    echo "支付成功，账户流水号：" . $balanceTransactionCode;
}
```

## 自定义支付方式

### 1. 继承 AbstractPaymentMethod

```php
use Dybasedev\LunaPrototype\Trade\Payment\AbstractPaymentMethod;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentResult;

class AlipayPaymentMethod extends AbstractPaymentMethod
{
    public function getName(): string
    {
        return $this->config->getName() ?? 'alipay';
    }
    
    public function getDisplayName(): string
    {
        return $this->config->getDisplayName() ?? '支付宝';
    }
    
    public function pay(
        Transaction $transaction,
        array $parameters = [],
        ?TransactionContext $context = null
    ): PaymentResult {
        // 创建支付宝订单
        $alipayOrder = $this->createAlipayOrder($transaction);
        
        // 返回待支付结果，包含跳转URL
        return PaymentResult::pending($alipayOrder['pay_url'], [
            'payment_no' => $alipayOrder['order_no'],
            'transaction_no' => $transaction->getTransactionNumber(),
            'amount' => $transaction->getAmount(),
        ]);
    }
    
    public function handleCallback(array $data, ?TransactionContext $context = null): PaymentResult
    {
        // 验证签名
        if (!$this->verifySignature($data)) {
            return PaymentResult::failure('Invalid signature');
        }
        
        // 检查支付状态
        if ($data['trade_status'] === 'TRADE_SUCCESS') {
            return PaymentResult::success([
                'payment_no' => $data['trade_no'],
                'transaction_no' => $data['out_trade_no'],
                'paid_amount' => $data['total_amount'],
                'paid_at' => $data['gmt_payment'],
            ]);
        }
        
        return PaymentResult::failure($data['trade_status']);
    }
    
    public function getCapabilities(): array
    {
        return [
            'supports_partial_payment' => false,
            'supports_refund' => true,
            'supports_query' => true,
            'supports_callback' => true,
            'requires_redirect' => true,
            'instant_payment' => false,
        ];
    }
}
```

### 2. 注册自定义支付方式

```php
$payment->registerMethod('alipay', AlipayPaymentMethod::class, [
    'app_id' => env('ALIPAY_APP_ID'),
    'private_key' => env('ALIPAY_PRIVATE_KEY'),
    'public_key' => env('ALIPAY_PUBLIC_KEY'),
    'gateway_url' => 'https://openapi.alipay.com/gateway.do',
    'notify_url' => url('/payment/alipay/notify'),
    'return_url' => url('/payment/alipay/return'),
]);
```

## 支付流程集成

### 1. 在交易流程处理器中集成支付

```php
class CustomTradeFlowHandler extends TradeFlowHandler
{
    public function handleStatusChange(
        Transaction $transaction,
        int $fromStatus,
        int $toStatus,
        array $context = []
    ): StatusChangeResult {
        // 当状态变更为已支付时
        if ($toStatus === StandardTransactionStatus::Paid->getCode()) {
            // 处理支付成功后的逻辑
            $this->onPaymentCompleted($transaction, $context);
        }
        
        return parent::handleStatusChange($transaction, $fromStatus, $toStatus, $context);
    }
    
    protected function onPaymentCompleted(Transaction $transaction, array $context): void
    {
        // 发送订单确认邮件
        // 通知仓库发货
        // 更新库存
        // 记录销售数据
    }
}
```

### 2. 支持多次支付的场景

```php
// 分期付款场景
class InstallmentPaymentHandler
{
    public function processInstallment(
        Transaction $transaction,
        int $installmentNumber,
        string $paymentMethod
    ): PaymentResult {
        $handler = luna_trade()->getFlowHandler($transaction->handler_id);
        
        // 计算本期应付金额
        $amount = $this->calculateInstallmentAmount($transaction, $installmentNumber);
        
        // 创建分期支付上下文
        $context = TransactionContext::make()
            ->withParameters([
                'installment_number' => $installmentNumber,
                'installment_amount' => $amount,
            ]);
        
        // 执行支付
        return $handler->processPayment($transaction, $paymentMethod, [], $context);
    }
}

// 补交费用场景
class AdditionalFeeHandler
{
    public function chargeAdditionalFee(
        Transaction $transaction,
        float $feeAmount,
        string $feeType,
        string $paymentMethod
    ): PaymentResult {
        $context = TransactionContext::make()
            ->withParameters([
                'fee_type' => $feeType,
                'fee_amount' => $feeAmount,
            ]);
        
        return luna_trade()
            ->getFlowHandler($transaction->handler_id)
            ->processPayment($transaction, $paymentMethod, [], $context);
    }
}
```

## 最佳实践

1. **支付密码验证**：对于涉及资金的支付方式，建议实现支付密码验证
2. **支付状态同步**：确保支付状态与交易状态保持同步
3. **异常处理**：充分处理支付过程中可能出现的各种异常
4. **日志记录**：记录所有支付操作的详细日志，便于问题排查
5. **安全考虑**：
   - 使用 HTTPS 传输敏感信息
   - 验证支付回调的签名
   - 防止重复支付
   - 设置支付超时时间

## 配置参考

### 完整配置示例

```php
LunaTradeConfigure::create()
    ->configurePayment(function (PaymentConfiguration $payment) {
        // 全局配置
        $payment->setGlobalConfig([
            'test_mode' => env('PAYMENT_TEST_MODE', false),
            'log_channel' => 'payment',
        ]);
        
        // 注册多个支付方式
        $payment->registerMethods([
            'balance' => [
                'class' => AssetsAccountPaymentMethod::class,
                'config' => [
                    'account_type' => 'balance',
                    'discount_rate' => 5,
                ],
            ],
            'alipay' => [
                'class' => AlipayPaymentMethod::class,
                'config' => [
                    'app_id' => env('ALIPAY_APP_ID'),
                ],
            ],
            'wechat' => [
                'class' => WechatPaymentMethod::class,
                'config' => [
                    'app_id' => env('WECHAT_APP_ID'),
                ],
            ],
        ]);
        
        // 设置优先级和默认支付方式
        $payment->setPriorities([
            'balance' => 100,
            'wechat' => 90,
            'alipay' => 80,
        ])->setDefaultMethod('balance');
        
        // 根据条件禁用某些支付方式
        if (!env('ALIPAY_ENABLED')) {
            $payment->disable('alipay');
        }
    })
    ->build();
```

## 总结

Trade 组件的支付系统提供了：

- **灵活的支付方式管理**：轻松添加、配置和管理多种支付方式
- **统一的支付接口**：所有支付方式遵循相同的接口规范
- **完整的支付流程**：支持支付、回调、查询、退款等完整流程
- **与交易系统的深度集成**：支付状态与交易状态自动同步
- **扩展性强**：可以轻松实现自定义支付方式

通过这种设计，开发者可以专注于业务逻辑，而不必关心支付的底层实现细节。