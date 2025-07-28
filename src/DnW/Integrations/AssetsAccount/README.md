# AssetsAccount 集成

本集成提供了与 Luna AssetsAccount 组件的无缝对接，实现用户资产账户的入金和出金功能。

## 功能特性

### 核心功能
- **账户类型配置**: 指定入金/出金使用的 AssetsAccount 账户类型
- **自动账户操作**: 入金时自动增加账户余额，出金时自动扣除
- **余额验证**: 出金时自动检查账户余额是否充足
- **事务保证**: 所有操作在事务中执行，确保数据一致性
- **完整日志**: 通过 AssetsAccount 的 ChangeLog 记录所有操作

## 安装使用

### 1. 前置要求

确保已经安装并配置了 AssetsAccount 组件，并创建了相应的账户类型：

```php
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;

$assetsAccount = app(LunaAssetsAccount::class);

// 创建账户类型（如果还没有创建）
$assetsAccount->createAccountType(
    'balance',           // 账户类型标识
    'default_handler',   // 处理器
    '余额账户',          // 显示名称
    '用户主要余额账户'   // 描述
);
```

### 2. 安装处理器

```php
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountInstaller;

// 安装默认的 AssetsAccount 处理器
$result = AssetsAccountInstaller::install();

// 返回创建的渠道
$depositChannel = $result['deposit_channel'];
$withdrawChannel = $result['withdraw_channel'];
```

### 3. 自定义账户类型

如果需要使用其他账户类型（如积分、代币等）：

```php
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\DnW\LunaDnW;

$lunaHandler = app(LunaHandler::class);
$lunaDnW = app(LunaDnW::class);

// 创建使用积分账户的入金处理器
$config = new Repository([
    'account_type' => 'points', // 使用积分账户类型
]);

$handler = $lunaHandler->createEntityHandler(
    'dnw',
    'points_deposit_handler',
    AssetsAccountDepositHandler::class,
    $config,
    '积分入金处理器',
    '入金到用户积分账户'
);

// 创建积分入金渠道
$pointsDepositChannel = $lunaDnW->createDepositChannel(
    'points_deposit',
    $handler->id,
    [
        'description' => '积分充值',
        'supported_currencies' => ['POINTS'],
    ]
);
```

## 处理器配置选项

### 配置参数

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `account_type` | string | 是 | AssetsAccount 账户类型标识（如 'balance', 'points' 等） |
| `enable_unit_conversion` | bool | 否 | 是否启用单位转换（默认: false） |
| `unit_conversion.from_unit` | string | 否 | 源单位（启用单位转换时使用） |
| `unit_conversion.to_unit` | string | 否 | 目标单位（启用单位转换时使用） |

## 使用示例

### 创建入金交易

```php
$transaction = $dnw->createDepositTransaction(
    $user, // 用户模型（SessionHolder）
    $depositChannel,
    '1000.00', // 金额
    [
        'currency_id' => 1,
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]
);

// 处理交易
$result = $dnw->processDeposit($transaction);

if ($result->isSuccess() && $result->isCompleted()) {
    // 入金成功，用户账户余额已增加
    $account = $assetsAccount->ownerAccount($user, 'balance');
    echo "当前余额: " . $account->available_balance;
}
```

### 创建出金交易

```php
// 先检查余额
$account = $assetsAccount->ownerAccount($user, 'balance');
if ($account->available_balance < 2000) {
    throw new \Exception('余额不足');
}

$transaction = $dnw->createWithdrawTransaction(
    $user,
    $withdrawChannel,
    '2000.00', // 总金额（包含手续费）
    [
        'currency_id' => 1,
        'fee' => '2.00', // 手续费
    ]
);

// 处理交易（会自动扣除账户余额）
$result = $dnw->processWithdraw($transaction);

if ($result->isSuccess()) {
    // 出金成功，账户已扣除 2000 元
    // 实际提现金额为 1998 元（扣除手续费）
}
```

### 查询账户变更记录

```php
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountChangeLog;

// 查询与 DnW 相关的账户变更
$logs = AssetsAccountChangeLog::where('account_id', $account->id)
    ->whereIn('event', ['dnw_deposit', 'dnw_withdraw'])
    ->latest()
    ->get();

foreach ($logs as $log) {
    echo sprintf(
        "%s: %s %s 元 (交易ID: %s)\n",
        $log->created_at,
        $log->event === 'dnw_deposit' ? '入金' : '出金',
        $log->change_value,
        $log->payload['transaction_id'] ?? 'N/A'
    );
}
```

## 注意事项

1. **账户类型必须存在**: 使用前确保配置的账户类型已在 AssetsAccount 中创建
2. **自动创建账户**: 首次操作时会自动为用户创建对应类型的账户
3. **余额检查**: 出金时会自动检查余额，不足时会抛出异常
4. **手续费处理**: 出金时扣除的是总金额（包含手续费）
5. **事务一致性**: 所有操作都在数据库事务中执行，失败会自动回滚

## 与其他支付渠道配合

AssetsAccount 集成通常作为内部账户系统，可以与其他支付渠道配合使用：

```php
// 1. 用户通过第三方支付充值到余额账户
$externalDeposit = $dnw->createDepositTransaction(
    $user,
    $digitalWalletChannel, // 数字钱包渠道
    '1000.00'
);

// 2. 支付成功后，余额自动增加（通过 AssetsAccount 集成）
$internalDeposit = $dnw->createDepositTransaction(
    $user,
    $assetsAccountChannel, // AssetsAccount 渠道
    '1000.00'
);

// 3. 用户使用余额进行消费或提现
```

## 单位转换支持

AssetsAccount 集成现在支持可选的单位转换功能，需要安装并配置 LunaUnitConversion 组件。

### 启用单位转换

```php
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

$config = new Repository([
    'account_type' => 'balance',
    'enable_unit_conversion' => true,
    'unit_conversion' => [
        'from_unit' => 'USD',  // 从美元
        'to_unit' => 'CNY',    // 转换为人民币
    ],
]);

$handler = $lunaHandler->createEntityHandler(
    'dnw',
    'usd_to_cny_deposit_handler',
    AssetsAccountDepositHandler::class,
    $config,
    'USD 到 CNY 入金处理器',
    '支持自动汇率转换的入金处理器'
);
```

### 动态指定源单位

在创建交易时，可以通过 options 参数动态指定源单位：

```php
$transaction = $dnw->createDepositTransaction(
    $user,
    $depositChannel,
    '100.00', // 100 美元
    [
        'from_unit' => 'USD', // 动态指定源单位
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]
);

// 处理后，将按照当前汇率转换为 CNY 并存入账户
```

### 注意事项

1. **前置要求**: 必须安装并配置 LunaUnitConversion 组件
2. **汇率配置**: 确保在 UnitConversion 中配置了相应的货币转换率
3. **错误处理**: 如果转换失败，将使用原始金额
4. **日志跟踪**: 所有转换操作都会记录在日志中

### 重要：与 UnitConversion 的 AssetsAccount 集成配合使用

当在 DnW 的 AssetsAccount 集成中启用单位转换时，强烈建议同时使用 UnitConversion 组件对 AssetsAccount 的集成，以确保数据一致性。

#### 为什么需要这样做？

1. **数据一致性**: UnitConversion 的 AssetsAccount 集成使用 `ConversionAwareAccountOperations` 类，它会在 `payload._unit_conversion` 字段中记录详细的转换信息
2. **审计跟踪**: 转换信息对于后续的对账、审计和故障排查至关重要
3. **功能完整性**: 只有使用了正确的操作类，才能完整记录和利用单位转换的所有功能

#### 如何配置？

1. **全局配置**（推荐）：

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

2. **自动检测**：

DnW 的 AssetsAccount 集成在启用单位转换时会自动尝试使用 `ConversionAwareAccountOperations`，但这只在单个交易中生效。为了系统的一致性，建议全局配置。

#### 可能的问题

如果未正确配置：

1. **转换信息丢失**: 虽然金额会被正确转换，但转换的详细信息（如原始金额、汇率等）不会被记录
2. **对账困难**: 没有原始金额信息，难以进行跨币种对账
3. **功能限制**: 无法使用 UnitConversion 提供的高级功能，如手续费计算、转换上下文等

## 扩展开发

可以继承处理器来实现自定义逻辑：

```php
class CustomAssetsDepositHandler extends AssetsAccountDepositHandler
{
    protected function doProcess(DepositTransaction $transaction): DepositResult
    {
        // 自定义逻辑，如赠送额外积分
        if ($transaction->amount >= 1000) {
            $this->giveBonus($transaction->owner);
        }
        
        return parent::doProcess($transaction);
    }
}
```

### 单位转换示例

继承处理器并根据业务需求自定义单位转换逻辑：

```php
class MultiCurrencyDepositHandler extends AssetsAccountDepositHandler
{
    protected function preprocessAmount(string $amount, array $options = []): string
    {
        // 首先调用父类处理标准单位转换
        $amount = parent::preprocessAmount($amount, $options);
        
        // 添加自定义的业务逻辑，如手续费率调整
        if (isset($options['apply_exchange_fee'])) {
            $exchangeFeeRate = 0.01; // 1% 汇率手续费
            $amount = bcmul($amount, (string)(1 - $exchangeFeeRate), 2);
        }
        
        return $amount;
    }
}
```

### 完整的集成示例

以下是一个完整的示例，展示如何正确配置和使用单位转换：

```php
// 1. 在 AppServiceProvider 中配置两个组件
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversionConfigure;
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount\ConversionAwareAccountOperations;

// 配置 AssetsAccount 使用转换感知的操作类
$this->registerModule(
    LunaAssetsAccountConfigure::create()
        ->useAccountOperationClass(ConversionAwareAccountOperations::class)
        ->build()
);

// 配置 UnitConversion
$this->registerModule(
    LunaUnitConversionConfigure::create()
        ->build()
);

// 2. 创建带单位转换的入金处理器
$handler = AssetsAccountInstaller::installCurrencyConversionHandler(
    'USD',      // 源货币
    'CNY',      // 目标货币
    'balance'   // 账户类型
);

// 3. 使用处理器创建交易
$transaction = $dnw->createDepositTransaction(
    $user,
    $channel,
    '100.00',   // 100 USD
    [
        'from_unit' => 'USD',
    ]
);

// 4. 处理交易
$result = $dnw->processDeposit($transaction);

// 5. 查看转换信息
$logs = AssetsAccountChangeLog::where('account_id', $account->id)
    ->where('event', 'dnw_deposit')
    ->latest()
    ->first();

// 如果正确配置，将看到转换信息
if (isset($logs->payload['_unit_conversion'])) {
    $conversion = $logs->payload['_unit_conversion'];
    echo "Original: {$conversion['original_amount']} {$conversion['from_unit']}\n";
    echo "Converted: {$conversion['converted_amount']} {$conversion['to_unit']}\n";
    echo "Rate: {$conversion['rate']}\n";
}
```