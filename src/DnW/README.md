# DnW（Deposit and Withdraw）出入金组件

DnW 组件为 Luna Prototype 提供完整的出入金（充值提现）功能支持。

## 功能特性

- **多渠道支持**：支持配置多个入金和出金渠道
- **灵活的处理器机制**：通过 Handler 接口实现不同的支付处理逻辑
- **完整的状态流转**：支持交易的完整生命周期管理
- **审核机制**：出金支持审核流程，可配置免审额度
- **账户绑定**：支持多种类型的收款账户绑定（金融账户、数字钱包、区块链地址等）
- **事件驱动**：通过 BusinessEvent 实现事件通知
- **审计日志**：完整记录交易状态变更历史

## 安装使用

### 1. 在模块配置中启用

```php
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;

LunaPrototype::withModules([
    // ...
    LunaDnWConfigure::class,
]);
```

### 2. 发布并运行迁移

```bash
php artisan vendor:publish --tag=luna-prototype-migrations
php artisan migrate
```

### 3. 在用户模型中使用

```php
use Dybasedev\LunaPrototype\DnW\Traits\HasDnW;

class User extends Model
{
    use HasDnW;
    // ...
}
```

## 基本使用

### 创建渠道

```php
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;

// 创建入金渠道
$depositChannel = DepositChannel::create([
    'name' => '金融转账',
    'handler_id' => 'manual',
    'config' => [
        'institution_name' => '金融机构名称',
        'account_identifier' => '1234567890',
    ],
    'is_active' => true,
    'sort' => 1,
]);

// 创建出金渠道
$withdrawChannel = WithdrawChannel::create([
    'name' => '金融账户提现',
    'handler_id' => 'manual',
    'config' => [
        'min_amount' => 100,
        'max_amount' => 50000,
    ],
    'is_active' => true,
    'sort' => 1,
]);
```

### 绑定账户

```php
// 绑定金融账户用于提现
$binding = $user->withdrawBindings()->create([
    'channel_id' => $withdrawChannel->id,
    'type' => 'financial_account',
    'account_name' => '张三',
    'account_number' => '6222021234567890123',
    'account_info' => [
        'institution_name' => '金融机构名称',
        'branch_name' => '分支机构名称',
    ],
]);

// 设为默认
$binding->setAsDefault();

// 验证账户
$binding->verify();
```

### 创建交易

```php
use Dybasedev\LunaPrototype\DnW\LunaDnW;

$dnw = app(LunaDnW::class);

// 创建入金交易
$depositTx = $dnw->createDepositTransaction($user, $depositChannel, 1000, [
    'fee' => 0,
    'extra_data' => ['order_no' => 'D20240101001'],
]);

// 创建出金交易
$withdrawTx = $dnw->createWithdrawTransaction($user, $withdrawChannel, 500, [
    'fee' => 2,
    'extra_data' => ['remark' => '提现到金融账户'],
]);

// 或者使用 Trait 提供的便捷方法
$depositTx = $user->createDepositTransaction($depositChannel, 1000);
$withdrawTx = $user->createWithdrawTransaction($withdrawChannel, 500);
```

### 处理交易

```php
use Dybasedev\LunaPrototype\DnW\LunaDnW;

$dnw = app(LunaDnW::class);

// 处理入金
$dnw->processDeposit($depositTx);

// 手动确认入金（对于 manual handler）
$handler = $dnw->getDepositHandler('manual');
if ($handler instanceof \Dybasedev\LunaPrototype\DnW\Handlers\ManualDepositHandler) {
    $handler->confirm($depositTx, [
        'third_party_id' => 'TXN20240101001',
        'operator_id' => 'admin_1',
    ]);
}

// 审核出金
if ($withdrawTx->needsReview()) {
    // 通过审核
    $withdrawTx->approve(['operator_id' => 'admin_1']);
    
    // 或拒绝
    // $withdrawTx->reject('余额不足', ['operator_id' => 'admin_1']);
}

// 处理出金
$dnw->processWithdraw($withdrawTx);
```

### 查询统计

```php
// 获取入金统计
$depositStats = $user->getDepositStatistics([
    'start_date' => '2024-01-01',
    'end_date' => '2024-12-31',
    'currency' => 'CNY',
]);

// 获取出金统计
$withdrawStats = $user->getWithdrawStatistics([
    'start_date' => '2024-01-01',
    'end_date' => '2024-12-31',
]);
```

## 自定义处理器

创建自定义的支付处理器：

```php
use Dybasedev\LunaPrototype\DnW\Handlers\AbstractDepositHandler;

class DigitalWalletDepositHandler extends AbstractDepositHandler
{
    public function getName(): string
    {
        return '数字钱包';
    }
    
    public function getDescription(): string
    {
        return '数字钱包扫码支付';
    }
    
    protected function doProcess(DepositTransaction $transaction): bool
    {
        // 调用数字钱包 API 创建支付订单
        $result = $this->createWalletPaymentOrder($transaction);
        
        if ($result['success']) {
            $transaction->third_party_id = $result['trade_no'];
            $transaction->extra_data = array_merge($transaction->extra_data ?? [], [
                'qr_code' => $result['qr_code'],
            ]);
            $transaction->save();
            return true;
        }
        
        return false;
    }
    
    // 实现其他必要方法...
}
```

注册自定义处理器：

```php
app(LunaDnWConfigure::class)->registerDepositHandler('digital_wallet', DigitalWalletDepositHandler::class);
```

## 事件监听

可以监听以下事件：

- `dnw.deposit.created` - 入金交易创建
- `dnw.deposit.processing` - 入金交易处理中
- `dnw.deposit.completed` - 入金交易完成
- `dnw.deposit.failed` - 入金交易失败
- `dnw.withdraw.created` - 出金交易创建
- `dnw.withdraw.reviewing` - 出金交易审核中
- `dnw.withdraw.approved` - 出金交易审核通过
- `dnw.withdraw.rejected` - 出金交易审核拒绝
- `dnw.withdraw.completed` - 出金交易完成
- `dnw.binding.created` - 账户绑定创建
- `dnw.binding.verified` - 账户绑定验证

## 配置选项

```php
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;

LunaPrototype::withModules([
    LunaDnWConfigure::class => function (LunaDnWConfigure $configure) {
        // 配置审核规则
        $configure->setWithdrawReview(true, 10000); // 启用审核，超过10000需要审核
        
        // 设置默认币种
        $configure->setDefaultCurrency('USD');
        
        // 启用/禁用交易日志
        $configure->setTransactionLog(true);
        
        // 设置绑定验证要求
        $configure->setBindingVerification(true);
        
        // 自定义模型类
        $configure->useDepositTransactionModel(MyDepositTransaction::class);
        $configure->useWithdrawTransactionModel(MyWithdrawTransaction::class);
        
        // 注册自定义处理器
        $configure->registerDepositHandler('digital_wallet', DigitalWalletDepositHandler::class);
        $configure->registerWithdrawHandler('financial_account', FinancialAccountWithdrawHandler::class);
    }
]);
```