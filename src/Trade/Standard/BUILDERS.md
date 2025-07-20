# 标准交易构建器

## 概述

标准交易构建器是 Trade 组件为标准交易流程提供的便捷工具类，它们继承自基础构建器类并实现了具体的业务逻辑。

## StandardTradeOperationBuilder

标准交易操作构建器提供了对标准交易流程中交易操作的链式调用接口。

### 功能特性

- 支持通过交易对象、交易ID或交易编号设置要操作的交易
- 提供上下文管理功能，用于传递额外的业务信息
- 封装了状态更新、交易完成、交易取消等常用操作
- 返回 `StatusChangeResult` 对象，提供丰富的操作结果信息

### 使用示例

```php
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeOperationBuilder;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionStatus;

// 创建构建器实例
$builder = new StandardTradeOperationBuilder();

// 或使用辅助函数
$builder = luna_trade_operation();

// 更新交易状态
$result = $builder
    ->transaction($transaction)  // 可以是 Transaction 对象、ID 或交易编号
    ->withContext([
        'payment_method' => 'alipay',
        'payment_no' => 'ALI20240101123456',
        'payment_time' => now(),
    ])
    ->updateStatus(StandardTransactionStatus::Paid->getCode());

if ($result->isSuccess()) {
    echo "交易支付成功";
} else {
    echo "支付失败：" . $result->getReason();
    if ($result->isRetryable()) {
        // 可以重试
    }
}

// 完成交易
$builder
    ->transaction($transactionId)
    ->withContext(['completion_note' => '订单已送达'])
    ->complete();

// 取消交易
$builder
    ->transaction($transactionNumber)
    ->withContext(['operator' => 'admin'])
    ->cancel('库存不足，无法发货');
```

## StandardTradeQueryBuilder

标准交易查询构建器提供了对标准交易流程中交易查询的链式调用接口。

### 功能特性

- 支持按所有者、状态、处理器、日期范围等条件过滤
- 提供便捷的状态过滤方法（如 `onlyCompleted()`、`onlyPending()`）
- 支持分页查询和单条记录查询
- 返回标准的 Laravel 分页器或集合对象

### 使用示例

```php
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeQueryBuilder;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionStatus;

// 创建构建器实例
$builder = new StandardTradeQueryBuilder();

// 或使用辅助函数
$builder = luna_trade_query();

// 查询用户的已支付交易
$paidTransactions = $builder
    ->forOwner($user)
    ->whereStatus(StandardTransactionStatus::Paid->getCode())
    ->whereDateBetween('2024-01-01', '2024-12-31')
    ->perPage(20)
    ->paginate();

// 查询已完成的交易
$completedTransactions = $builder
    ->forOwner($user)
    ->onlyCompleted()
    ->whereHandler('standard')
    ->get();  // 返回 Collection

// 获取最新的一笔交易
$latestTransaction = $builder
    ->forOwner($user)
    ->first();

// 复杂查询示例
$transactions = luna_trade_query()
    ->forOwner($user)
    ->whereStatus(StandardTransactionStatus::Paid->getCode())
    ->whereHandler('standard')
    ->whereDateBetween(now()->subDays(30), now())
    ->perPage(50)
    ->paginate();

// 遍历结果
foreach ($transactions as $transaction) {
    echo "交易号：" . $transaction->transaction_number;
    echo "金额：" . $transaction->amount;
}
```

## 与基础构建器的关系

### 继承结构

```
TradeOperationBuilder (抽象基类)
    └── StandardTradeOperationBuilder (标准实现)

TradeQueryBuilder (抽象基类)
    └── StandardTradeQueryBuilder (标准实现)
```

### 扩展点

如果需要自定义交易流程，可以：

1. 继承基础构建器类
2. 实现抽象方法
3. 添加特定于业务的方法
4. 在 `LunaTradeConfigure` 中注册

```php
// 自定义操作构建器
class CustomTradeOperationBuilder extends TradeOperationBuilder
{
    public function updateStatus(int $status): StatusChangeResult
    {
        // 实现自定义的状态更新逻辑
        // 例如：添加审批流程、发送通知等
    }
    
    public function approve(): StatusChangeResult
    {
        // 添加业务特定的操作
        return $this->updateStatus(CustomStatus::APPROVED);
    }
}

// 自定义查询构建器
class CustomTradeQueryBuilder extends TradeQueryBuilder
{
    public function whereApproved(): static
    {
        $this->filters['status'] = CustomStatus::APPROVED;
        return $this;
    }
    
    public function whereNeedsApproval(): static
    {
        $this->filters['needs_approval'] = true;
        return $this;
    }
}
```

## 最佳实践

1. **使用辅助函数**：优先使用 `luna_trade_operation()` 和 `luna_trade_query()` 辅助函数
2. **链式调用**：充分利用链式调用提高代码可读性
3. **上下文传递**：通过 `withContext()` 传递业务相关信息
4. **错误处理**：始终检查 `StatusChangeResult` 的返回结果
5. **性能考虑**：合理设置分页大小，避免一次加载过多数据

## 总结

标准交易构建器遵循 Luna 框架的设计理念：

- **原子性**：每个构建器专注于单一职责
- **可组合**：构建器可以与其他组件自由组合
- **可扩展**：通过继承基类轻松创建自定义构建器
- **易用性**：提供流畅的链式调用接口

这种设计使得标准交易流程易于使用，同时为自定义业务流程保留了充分的灵活性。