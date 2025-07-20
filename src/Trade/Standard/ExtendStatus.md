# 扩展交易状态指南

标准交易流程提供了最基础的状态（待支付、已支付、已完成、已取消、已过期），如果您的业务需要更多状态（如发货、收货、退款等），可以按照以下方式扩展。

## 1. 创建自定义状态类

```php
namespace App\Trade\Status;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

class ShippedStatus extends TransactionStatus
{
    protected string $key = 'shipped';
    protected string $name = '已发货';
    protected string $description = '卖家已发货，等待买家收货';
    
    public function getAllowedTransitions(): array
    {
        return ['received', 'refunding'];
    }
    
    public function onReached(
        TradeTransaction $transaction,
        ?TransactionStatus $fromStatus,
        array $context = []
    ): void {
        // 记录发货信息
        $payload = $transaction->payload ?: [];
        $payload['shipped_at'] = now()->toDateTimeString();
        $payload['tracking_no'] = $context['tracking_no'] ?? '';
        $payload['shipping_company'] = $context['shipping_company'] ?? '';
        $transaction->payload = $payload;
        
        // 设置自动收货时间
        $autoReceiveDays = $context['auto_receive_days'] ?? 15;
        $payload['auto_receive_at'] = now()->addDays($autoReceiveDays)->toDateTimeString();
        $transaction->payload = $payload;
    }
}
```

## 2. 创建扩展的状态管理器

```php
namespace App\Trade;

use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionStatusManager;
use App\Trade\Status\ShippedStatus;
use App\Trade\Status\ReceivedStatus;
use App\Trade\Status\RefundingStatus;
use App\Trade\Status\RefundedStatus;

class ExtendedStatusManager extends StandardTransactionStatusManager
{
    protected array $statusClasses = [
        // 继承标准状态
        'pending_payment' => \Dybasedev\LunaPrototype\Trade\Standard\Status\PendingPaymentStatus::class,
        'paid' => \Dybasedev\LunaPrototype\Trade\Standard\Status\PaidStatus::class,
        'completed' => \Dybasedev\LunaPrototype\Trade\Standard\Status\CompletedStatus::class,
        'canceled' => \Dybasedev\LunaPrototype\Trade\Standard\Status\CanceledStatus::class,
        'expired' => \Dybasedev\LunaPrototype\Trade\Standard\Status\ExpiredStatus::class,
        
        // 添加自定义状态
        'shipped' => ShippedStatus::class,
        'received' => ReceivedStatus::class,
        'refunding' => RefundingStatus::class,
        'refunded' => RefundedStatus::class,
    ];
}
```

## 3. 修改已有状态的转换规则

```php
namespace App\Trade\Status;

use Dybasedev\LunaPrototype\Trade\Standard\Status\PaidStatus as BasePaidStatus;

class PaidStatus extends BasePaidStatus
{
    public function getAllowedTransitions(): array
    {
        // 修改为：支付后可以发货或退款
        return ['shipped', 'refunding', 'canceled'];
    }
}
```

## 4. 创建自定义交易流程处理器

```php
namespace App\Trade;

use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeFlowHandler;

class ExtendedTradeFlowHandler extends StandardTradeFlowHandler
{
    public function __construct()
    {
        // 使用扩展的状态管理器
        $this->statusManager = new ExtendedStatusManager();
    }
    
    protected function handleStatusChange(
        TradeTransaction $transaction,
        int $fromStatus,
        int $toStatus,
        array $context = []
    ): bool {
        $result = parent::handleStatusChange($transaction, $fromStatus, $toStatus, $context);
        
        // 添加额外的状态处理逻辑
        $toStatusObj = $this->statusManager->getStatusByCode($toStatus);
        
        switch ($toStatusObj->getKey()) {
            case 'shipped':
                $this->handleShipped($transaction, $context);
                break;
                
            case 'received':
                $this->handleReceived($transaction, $context);
                break;
                
            case 'refunding':
                $this->handleRefunding($transaction, $context);
                break;
        }
        
        return $result;
    }
    
    protected function handleShipped(TradeTransaction $transaction, array $context): void
    {
        // 发货后的业务逻辑
        // 例如：发送物流通知
    }
    
    protected function handleReceived(TradeTransaction $transaction, array $context): void
    {
        // 收货后的业务逻辑
        // 例如：触发结算
    }
    
    protected function handleRefunding(TradeTransaction $transaction, array $context): void
    {
        // 退款中的业务逻辑
        // 例如：冻结相关资金
    }
}
```

## 5. 注册和使用

```php
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;
use App\Trade\ExtendedTradeFlowHandler;

// 配置时注册自定义处理器
$configure = LunaTradeConfigure::create()
    ->registerFlowHandler('extended', ExtendedTradeFlowHandler::class)
    ->build();

// 使用时指定处理器
$transaction = luna_trade()->createTransaction(
    $user,
    'extended',  // 使用扩展的处理器
    $product,
    ['quantity' => 1]
);

// 状态转换示例
// 发货
luna_trade()->updateTransactionStatus(
    $transaction,
    short_hash_code('shipped'),
    [
        'tracking_no' => 'SF1234567890',
        'shipping_company' => '顺丰速运'
    ]
);

// 确认收货
luna_trade()->updateTransactionStatus(
    $transaction,
    short_hash_code('received'),
    []
);
```

## 状态流程图示例

### 标准流程（简化版）
```
待支付 -> 已支付 -> 已完成
  ↓         ↓
已过期    已取消
```

### 扩展流程（电商完整版）
```
待支付 -> 已支付 -> 已发货 -> 已收货 -> 已完成
  ↓         ↓         ↓          ↓
已过期    已取消   退款中    退款中
            ↓         ↓          ↓
          已退款    已退款     已退款
```

## 最佳实践

1. **保持状态单一职责**：每个状态只负责自己的业务逻辑
2. **使用事件驱动**：在状态变更时触发领域事件，解耦业务逻辑
3. **记录状态历史**：保存每次状态变更的记录，便于追踪
4. **处理并发**：使用数据库锁或乐观锁防止并发状态变更
5. **异常处理**：状态变更失败时要有合适的回滚机制

## 注意事项

1. 扩展状态时，确保状态转换规则的合理性
2. 避免创建过多的中间状态，保持流程简洁
3. 考虑状态的最终性，避免出现循环状态
4. 为每个状态提供清晰的业务含义和转换条件