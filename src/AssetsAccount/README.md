# AssetsAccount 资产账户模块

AssetsAccount 是 Luna Prototype 的核心模块之一，提供了完整的资产账户管理功能，可以适配绝大多数涉及资产、积分、虚拟货币、出入金等相关业务场景。

## 核心概念

### 账户类型（Account Type）
账户类型定义了账户的业务属性和处理逻辑，比如余额账户、积分账户、信用账户等。每个账户类型可以关联特定的处理器（Handler）来实现不同的业务逻辑。

### 账户（Account）
账户是实际存储资产的实体，每个账户都属于特定的账户类型，并关联到特定的所有者（如用户、商户等）。

### 余额类型（Balance Type）
每个账户都支持三种余额类型：
- **可用余额（Available Balance）**：可以自由使用的余额
- **冻结余额（Frozen Balance）**：被冻结不可使用的余额，通常用于交易中的资金冻结
- **锁定余额（Locked Balance）**：被锁定的余额，通常用于提现等场景

### 账户操作（Account Operation）
对账户的所有变更都通过账户操作来完成，包括余额更新、转账等。系统会自动记录所有操作日志，确保资金流向可追溯。

## 功能特性

- ✅ **多账户类型**：支持创建任意数量的账户类型，满足不同业务需求
- ✅ **层级账户结构**：支持父子账户关系，可构建复杂的账户体系
- ✅ **多余额类型**：每个账户支持可用、冻结、锁定三种余额
- ✅ **批量操作**：支持在一个事务中执行多个账户操作
- ✅ **原子性保证**：所有操作都在事务中执行，确保数据一致性
- ✅ **透支控制**：可配置是否允许账户余额为负
- ✅ **完整日志**：自动记录所有账户变更日志
- ✅ **性能优化**：使用缓存和批量操作提高性能
- ✅ **扩展性强**：通过处理器系统支持自定义业务逻辑

## 安装配置

### 1. 注册模块

在 `AppServiceProvider` 中注册资产账户模块：

```php
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;

class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册资产账户模块
        $this->registerModule(
            LunaAssetsAccountConfigure::create()->build()
        );
    }
}
```

### 2. 运行迁移

```bash
php artisan migrate
```

这会创建以下数据表：
- `luna_assets_account_types` - 账户类型表
- `luna_assets_accounts` - 账户表
- `luna_assets_account_change_logs` - 账户变更日志表

### 3. 配置处理器（可选）

如果需要自定义账户处理逻辑，可以创建自定义处理器：

```php
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;

public function customRegister(): void
{
    // 注册自定义账户处理器
    $this->extendModule(function() {
        return LunaHandlerConfigure::create()
            ->group('account_handlers', '账户处理器', function($register) {
                $register->handler(PointsAccountHandler::class);
                $register->handler(CreditAccountHandler::class);
            })
            ->build();
    });
}
```

## 使用指南

### 获取模块实例

```php
// 使用辅助函数
$assetsAccount = luna_assets_account();

// 或通过容器
$assetsAccount = app(LunaAssetsAccount::class);
```

### 创建账户类型

```php
// 创建基础账户类型
$balanceType = $assetsAccount->createAccountType(
    'balance',           // 唯一标识名
    'default_handler',   // 处理器名称
    '余额账户',          // 显示名称
    '用户主要余额账户'   // 描述
);

// 创建带配置的账户类型
$pointsType = $assetsAccount->createAccountType(
    'points',
    'points_handler', 
    '积分账户',
    '用户积分账户',
    ['exchange_rate' => 100]  // 自定义配置
);

// 创建子账户类型
$subWallet = $assetsAccount->createAccountType(
    'sub_wallet',
    'default_handler',
    '子钱包',
    '特定用途的子账户',
    null,                    // 配置
    $balanceType            // 父账户类型
);
```

### 获取账户类型

```php
// 获取所有账户类型（带缓存）
$types = $assetsAccount->getAllAccountTypes();

// 获取特定账户类型
$balanceType = $types->firstWhere('name', 'balance');
```

### 为用户创建账户

创建账户类型时，系统会自动为所有已存在的用户创建对应账户。对于新用户：

```php
// 为用户创建特定类型账户
$account = $assetsAccount->createOwnerAccount(
    $user,           // 所有者对象
    'balance'        // 账户类型名称
);

// 获取用户的账户
$account = $assetsAccount->ownerAccount($user, 'balance');

// 获取用户的所有主账户
$accounts = $assetsAccount->ownerMainAccounts($user);
```

### 账户余额操作

#### 更新账户余额

```php
// 增加余额
$operation = $assetsAccount->createAccountOperation();
$operation->operation(
    luna_account_update()
        ->account($user, 'balance')     // 指定账户
        ->available()                   // 操作可用余额
        ->event('recharge')             // 事件标识
        ->payload(['order_id' => 123]) // 附加数据
        ->increase(100.50)              // 增加金额
);
$operation->submit();

// 减少余额
$operation->operation(
    luna_account_update()
        ->account($user, 'balance')
        ->available()
        ->event('purchase')
        ->payload(['product_id' => 456])
        ->decrease(50.25)
);
$operation->submit();  // 默认不允许透支

// 冻结余额（从可用余额转到冻结余额）
$operation->operation(
    luna_account_update()
        ->account($user, 'balance')
        ->availableToFrozen()           // 从可用到冻结
        ->event('freeze_for_withdraw')
        ->increase(30)
);
$operation->submit();
```

#### 账户间转账

```php
// 用户间转账
$operation = $assetsAccount->createAccountOperation();
$operation->operation(
    luna_account_transfer()
        ->from($userA, 'balance')       // 转出账户
        ->fromAvailable()               // 从可用余额转出
        ->to($userB, 'balance')         // 转入账户  
        ->toAvailable()                 // 转入可用余额
        ->event('transfer')
        ->payload(['note' => '转账备注'])
        ->amount(200)
);
$operation->submit();

// 同一用户不同账户类型间转换
$operation->operation(
    luna_account_transfer()
        ->from($user, 'balance')
        ->fromAvailable()
        ->to($user, 'points')
        ->toAvailable()
        ->event('balance_to_points')
        ->amount(100)
);
$operation->submit();

// 余额类型间转换（如解冻）
$operation->operation(
    luna_account_transfer()
        ->from($user, 'balance')
        ->fromFrozen()                  // 从冻结余额
        ->to($user, 'balance')  
        ->toAvailable()                 // 到可用余额
        ->event('unfreeze')
        ->amount(30)
);
$operation->submit();
```

### 批量操作

可以在一个事务中执行多个操作：

```php
$operation = $assetsAccount->createAccountOperation();

// 添加多个操作
$operation->operation(
    luna_account_update()
        ->account($userA, 'balance')
        ->available()
        ->event('bonus')
        ->increase(10)
);

$operation->operation(
    luna_account_update()
        ->account($userB, 'balance')
        ->available()
        ->event('bonus')
        ->increase(10)
);

$operation->operation(
    luna_account_transfer()
        ->from($userC, 'balance')
        ->to($userD, 'balance')
        ->amount(50)
);

// 一次性提交所有操作
$operation->submit();
```

### 透支控制

```php
// 允许透支（余额可以为负）
$operation->submit(true);

// 不允许透支（默认）
$operation->submit(false);
// 或
$operation->submit();
```

### 查询账户信息

```php
// 获取账户对象
$account = $assetsAccount->ownerAccount($user, 'balance');

// 查看各类余额
echo $account->available_balance;  // 可用余额
echo $account->frozen_balance;     // 冻结余额
echo $account->locked_balance;     // 锁定余额

// 获取总余额
$total = $account->available_balance 
       + $account->frozen_balance 
       + $account->locked_balance;

// 查询子账户
if ($account->parent_id) {
    $parentAccount = AssetsAccount::find($account->parent_id);
}

// 查询账户变更日志
$logs = AssetsAccountChangeLog::where('account_id', $account->id)
    ->latest()
    ->paginate();
```

## 最佳实践

### 1. 账户类型设计

根据业务需求合理设计账户类型：

```php
// 电商场景
- balance (余额账户)
  - refund_balance (退款专用子账户)
  - gift_balance (赠送余额子账户)
- points (积分账户)
- coupon (优惠券账户)

// 游戏场景  
- gold (金币账户)
- diamond (钻石账户)
- energy (体力账户)
- item_backpack (道具背包账户)
```

### 2. 使用事件标识

为每种业务操作定义清晰的事件标识，便于日志追踪：

```php
// 定义业务事件
$events = [
    'recharge' => '充值',
    'withdraw' => '提现',
    'purchase' => '购买商品',
    'refund' => '退款',
    'transfer_in' => '转账收入',
    'transfer_out' => '转账支出',
    'system_bonus' => '系统奖励',
    'freeze_for_order' => '订单冻结',
    'unfreeze_order' => '订单解冻'
];
```

### 3. 错误处理

```php
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();
    
    $operation = $assetsAccount->createAccountOperation();
    $operation->operation(
        luna_account_update()
            ->account($user, 'balance')
            ->available()
            ->decrease(100)
    );
    
    $result = $operation->submit();
    
    if (!$result) {
        throw new \Exception('余额不足');
    }
    
    // 其他业务逻辑...
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // 处理错误
}
```

### 4. 性能优化

- 使用批量操作减少数据库交互
- 账户类型信息会自动缓存，避免重复查询
- 对于高并发场景，考虑使用队列异步处理非关键操作

### 5. 安全建议

- 所有金额操作都应该在事务中进行
- 重要操作前进行余额校验
- 记录详细的操作日志用于审计
- 定期对账确保数据一致性

## 扩展开发

### 自定义账户处理器

```php
use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;

class PointsAccountHandler extends BaseHandler
{
    public function name(): string
    {
        return 'points_handler';
    }
    
    public function description(): string
    {
        return '积分账户处理器';
    }
    
    // 实现自定义逻辑
    public function beforeIncrease($account, $amount)
    {
        // 积分翻倍活动
        if ($this->isDoublePointsEvent()) {
            return $amount * 2;
        }
        return $amount;
    }
}
```

### 监听账户变更事件

可以通过 Laravel 的模型事件监听账户变更：

```php
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;

AssetsAccount::updated(function ($account) {
    // 账户余额变更后的处理
    if ($account->isDirty('available_balance')) {
        // 发送通知等
    }
});
```

## API 参考

### LunaAssetsAccount

| 方法 | 说明 | 参数 | 返回值 |
|------|------|------|--------|
| `createAccountType()` | 创建账户类型 | name, handler, displayName, description, config, parent | AssetsAccountType |
| `getAllAccountTypes()` | 获取所有账户类型 | withoutCache (bool) | Collection |
| `createOwnerAccount()` | 为所有者创建账户 | owner, accountTypeName, parent | AssetsAccount |
| `ownerAccount()` | 获取所有者的账户 | owner, accountTypeName, parent | AssetsAccount\|null |
| `ownerMainAccounts()` | 获取所有者的主账户 | owner | Collection |
| `createAccountOperation()` | 创建账户操作对象 | - | AccountOperations |

### 辅助函数

| 函数 | 说明 | 返回值 |
|------|------|--------|
| `luna_assets_account()` | 获取资产账户实例 | LunaAssetsAccount |
| `luna_account_update()` | 创建账户更新构建器 | AccountUpdateOperationBuilder |
| `luna_account_transfer()` | 创建账户转账构建器 | AccountTransferOperationBuilder |

## 常见问题

### Q: 如何实现账户余额的定时解冻？

A: 可以结合 Schedule 模块创建定时任务，查询需要解冻的记录并执行解冻操作。

### Q: 如何限制用户的日/月提现额度？

A: 可以在账户类型的配置中设置限额，并在自定义处理器中实现限额检查逻辑。

### Q: 如何实现多币种账户？

A: 可以为每种币种创建独立的账户类型，或者结合 UnitConversion 模块实现多币种支持。

### Q: 如何保证高并发下的账户安全？

A: 系统已经通过数据库事务和行锁保证了并发安全。对于极高并发场景，建议：
1. 使用队列将操作异步化
2. 实现账户余额缓存和预检查
3. 对关键操作增加分布式锁

## 更新日志

### v1.0.0
- 初始版本发布
- 支持多账户类型和层级结构
- 支持三种余额类型
- 完整的账户操作和日志记录

---

更多信息请参考 [Luna Prototype 文档](../../README.md)