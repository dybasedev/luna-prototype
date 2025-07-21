# HoldingObject 持有对象组件

## 简介

HoldingObject 组件提供了业务系统中成员持有状态的管理功能，能够基于此实现很多意想不到的功能，提供了极大的灵活性。通过持有状态判定可以实现签到、限购、抽奖等功能。

例如：
- **签到功能**：通过签到唯一持有记录判定持续签到时间或当日是否签到
- **限购功能**：通过持有记录限制用户购买数量
- **抽奖功能**：通过唯一持有记录判定还有多少次可用抽奖次数

## 核心概念

### UniqueObject（唯一对象）

唯一对象是指在系统中每个所有者只能持有一次的对象（除非明确允许多次持有）。通过数据库唯一索引保证其不会重复。

### 持有状态

组件预定义了以下持有状态（使用枚举类型 `HoldingStatus`）：
- `Normal` (1) - 正常
- `Frozen` (2) - 冻结
- `Used` (3) - 已使用
- `Expired` (4) - 已过期
- `Cancelled` (5) - 已取消
- `Invalid` (6) - 失效
- `Disabled` (7) - 禁用

枚举提供了便捷的辅助方法：
- `isNormal()` - 是否为正常状态
- `isFrozen()` - 是否为冻结状态
- `isInvalid()` - 是否为失效状态
- `isDisabled()` - 是否为禁用状态
- `isActive()` - 是否为活跃状态（正常或冻结）
- `isAvailable()` - 是否为可用状态（仅正常）
- `isFinal()` - 是否为最终状态
- `getDisplayName()` - 获取状态显示名称

## 使用方法

### 1. 注册唯一对象

首先需要创建一个继承自 `UniqueObject` 的类：

```php
use Dybasedev\LunaPrototype\HoldingObject\UniqueObject;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

class DailyCheckInObject extends UniqueObject
{
    protected string $name = 'daily-checkin';
    
    // 是否允许多次持有（用于累计签到次数）
    protected(set) bool $enableHoldMultiple = true;
    
    // 最大持有数量限制（null 表示不限制）
    protected(set) ?float $maxQuantity = null;
    
    // 单次增加的最大数量（null 表示不限制）
    protected(set) ?float $maxIncreaseQuantity = 1.0;
    
    // 单次减少的最大数量（null 表示不限制）
    protected(set) ?float $maxDecreaseQuantity = 1.0;
    
    // 格式化对象ID（这里使用日期作为ID）
    public function reformatId(string|int $id): string|int
    {
        // 使用固定ID，配合 Schedule 组件定期清理
        return 1;
    }
    
    // 权限检查
    public function permit(SessionHolder $owner, string|int $objectId, array $payload = []): bool
    {
        // 可以在这里检查用户是否有权限签到
        return true;
    }
    
    // 验证载荷数据
    public function validatePayload(array $payload): bool
    {
        // 验证签到相关数据
        return true;
    }
    
    // 自定义数量超限消息
    public function getQuantityExceededMessage(float $currentQuantity, float $requestedQuantity, array $context = []): string
    {
        return '今日已签到';
    }
}
```

然后在服务提供者中注册：

```php
use Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObjectConfigure;

luna_module_configure(LunaHoldingObjectConfigure::class, function ($configure) {
    $configure->registerUniqueObject('daily-checkin', DailyCheckInObject::class);
});
```

### 2. 创建持有记录

```php
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

// 创建签到记录
$owner = SessionHolder::fromModel($user);
$holding = luna_holding_object()->createUniqueHolding(
    $owner,
    'daily-checkin',
    1, // 使用固定ID，配合 Schedule 组件定期清理
    [
        'check_in_time' => now()->toDateTimeString(),
        'ip' => request()->ip(),
        'device' => request()->header('User-Agent'),
    ],
    1.0, // 数量
    null, // 单位ID
    'holding.daily_checkin' // 业务事件ID
);
```

> **注意**：签到功能使用固定ID（如 `1`），通过数据库唯一索引确保每个用户每天只能签到一次。配合 Foundation/Schedule 组件可以定期清理过期的签到记录。

#### 使用 BusinessEvent 记录操作

HoldingObject 组件集成了 Foundation/BusinessEvent 组件，可以在变更日志中记录业务事件：

```php
// 创建持有记录时传入事件ID
$holding = luna_holding_object()->createUniqueHolding(
    $owner,
    'lottery-chance',
    $lotteryId,
    [
        'source' => 'daily_login',
        'grant_time' => now()->toDateTimeString(),
    ],
    3.0, // 增加3次
    null, // 单位ID
    'holding.grant_chance' // 业务事件ID
);

// 更新状态时传入事件ID
luna_holding_object()->updateUniqueHoldingStatus(
    $owner,
    'daily-checkin',
    1,
    HoldingStatus::Used,
    [
        'reward_claimed_at' => now()->toDateTimeString(),
    ],
    'holding.status_update' // 业务事件ID
);
```

通过传入 `event_id`，变更日志会关联到对应的业务事件，便于：
- 生成人类可读的操作消息（通过 BusinessEvent 的 formatter）
- 按业务事件类型统计和分析
- 追踪操作上下文

### 3. 检查是否持有

```php
// 检查今日是否已签到
$hasCheckedIn = luna_holding_object()->hasUniqueHolding(
    $owner,
    'daily-checkin',
    1
);

if ($hasCheckedIn) {
    return response()->json(['message' => '今日已签到']);
}
```

### 4. 获取持有记录

```php
// 获取今日签到记录
$todayCheckIn = luna_holding_object()->getUniqueHolding(
    $owner,
    'daily-checkin',
    1
);

// 获取用户所有签到记录
$allCheckIns = luna_holding_object()->getOwnerHoldings($owner, [
    'object_type' => 'daily-checkin',
    'status' => HoldingStatus::Normal->value,
]);

// 计算连续签到天数
$consecutiveDays = $this->calculateConsecutiveDays($allCheckIns);
```

### 5. 增加/减少持有数量

对于允许多次持有的对象，可以使用原子操作来增减数量：

```php
// 增加抽奖次数
$holding = luna_holding_object()->increaseUniqueHoldingQuantity(
    $owner,
    'lottery-chance',
    $lotteryId,
    3.0, // 增加3次
    ['source' => 'daily_login'],
    'holding.grant_chance'
);

// 减少抽奖次数（使用抽奖）
$holding = luna_holding_object()->decreaseUniqueHoldingQuantity(
    $owner,
    'lottery-chance',
    $lotteryId,
    1.0, // 使用1次
    ['used_at' => now()->toDateTimeString()],
    'holding.use_chance'
);
```

这些方法使用数据库行锁确保原子性，避免并发问题。

### 6. 更新持有状态

```php
// 将签到记录标记为已使用（例如已领取奖励）
luna_holding_object()->updateUniqueHoldingStatus(
    $owner,
    'daily-checkin',
    1,
    HoldingStatus::Used,
    ['reward_claimed_at' => now()->toDateTimeString()]
);
```

### 7. 使用缓存提升性能

对于高频检查的场景，组件提供了存在性缓存功能：

```php
// 检查是否已签到（使用缓存）
$hasCheckedIn = luna_holding_object()->hasUniqueHolding(
    $owner,
    'daily-checkin',
    1
);

// 强制不使用缓存
$hasCheckedIn = luna_holding_object()->hasUniqueHolding(
    $owner,
    'daily-checkin',
    1,
    true // forceNoCache
);

// 清除特定对象的缓存
luna_holding_object()->clearExistenceCache($owner, 'daily-checkin', 1);

// 批量清除用户的缓存
luna_holding_object()->clearExistenceCacheBatch($owner, 'daily-checkin');
luna_holding_object()->clearExistenceCacheBatch($owner); // 清除该用户所有对象缓存
```

### 8. 查询构建器

#### 使用传统方式

```php
// 获取所有今日签到的用户
$todayHolders = luna_holding_object()
    ->queryUniqueHoldings(null, 'daily-checkin')
    ->where('object_id', 1)
    ->where('status', HoldingStatus::Normal->value)
    ->with('owner')
    ->get();

// 统计今日签到人数
$checkInCount = luna_holding_object()
    ->queryUniqueHoldings(null, 'daily-checkin')
    ->where('object_id', 1)
    ->count();
```

#### 使用查询参数构造器

```php
// 查询用户的所有正常状态持有记录
$params = holding_query()
    ->owner($owner)
    ->normal()
    ->latest()
    ->limit(20);
    
$holdings = luna_holding_object()->getOwnerHoldingsWithParams($params);

// 查询特定对象的所有持有者
$params = holding_query()
    ->object('lottery-chance', $lotteryId)
    ->minQuantity(1)
    ->orderBy('quantity', 'desc')
    ->limit(10);
    
$topHolders = luna_holding_object()->getObjectHoldersWithParams($params);

// 统计活跃签到用户
$count = luna_holding_object()->countWithParams(
    holding_query()
        ->object('daily-checkin', 1)
        ->normal()
);

// 分页查询
$params = holding_query()
    ->owner($owner)
    ->object('coupon')
    ->status(HoldingStatus::Normal)
    ->page(2, 20); // 第2页，每页20条
    
$coupons = luna_holding_object()->queryWithParams($params)->get();

// 复杂查询示例
$params = holding_query()
    ->object('product-limit')
    ->quantityRange(1, 10)  // 数量在1-10之间
    ->latest()
    ->limit(100);
    
// 获取查询构建器进行更复杂的操作
$query = luna_holding_object()->queryWithParams($params);
$query->whereDate('created_at', today())
      ->with(['owner' => function ($q) {
          $q->select('id', 'name', 'email');
      }]);
      
$results = $query->get();
```

## 高级用法

### 限购功能示例

```php
class ProductPurchaseLimitObject extends UniqueObject
{
    protected string $name = 'product-purchase-limit';
    
    public bool $enableHoldMultiple = true;
    
    public string $conflictMessage = '您已达到该商品的购买上限';
    
    public function permit(SessionHolder $owner, string|int $objectId, array $payload = []): bool
    {
        // 检查用户购买权限
        $product = Product::find($objectId);
        if (!$product || !$product->is_limited) {
            return true;
        }
        
        // 获取已购买数量
        $purchased = luna_holding_object()->getUniqueHolding($owner, $this->name, $objectId);
        if ($purchased && $purchased->quantity >= $product->limit_per_user) {
            return false;
        }
        
        return true;
    }
}
```

### 抽奖次数管理示例

```php
class LotteryChanceObject extends UniqueObject
{
    protected string $name = 'lottery-chance';
    
    protected(set) bool $enableHoldMultiple = true;
    
    // 设置最大持有数量为100次
    protected(set) ?float $maxQuantity = 100.0;
    
    // 单次最多增加10次（null 表示不限制）
    protected(set) ?float $maxIncreaseQuantity = 10.0;
    
    // 单次最多使用5次（null 表示不限制）
    protected(set) ?float $maxDecreaseQuantity = 5.0;
    
    public function createdHolding($holding): void
    {
        // 记录获得抽奖机会的日志
        activity()
            ->performedOn($holding)
            ->causedBy($holding->owner)
            ->log('获得抽奖机会');
    }
    
    public function getInsufficientQuantityMessage(float $currentQuantity, float $requestedQuantity, array $context = []): string
    {
        return sprintf('抽奖次数不足，当前仅有 %d 次', (int)$currentQuantity);
    }
}

// 增加抽奖次数
luna_holding_object()->createUniqueHolding(
    $owner,
    'lottery-chance',
    $lotteryId,
    ['source' => 'daily_login'],
    3.0 // 增加3次
);

// 使用抽奖次数（原子操作）
try {
    $holding = luna_holding_object()->decreaseUniqueHoldingQuantity(
        $owner,
        'lottery-chance',
        $lotteryId,
        1.0,
        ['used_at' => now()],
        'holding.use_chance'
    );
    
    // 执行抽奖逻辑...
    $prize = drawLottery();
    
    echo "抽奖成功！剩余次数：{$holding->quantity}";
} catch (LunaException $e) {
    echo $e->getDisplayMessage(); // 显示友好的错误消息
}
```

## 配置选项

在 `LunaHoldingObjectConfigure` 中可以通过链式调用配置：

```php
luna_module_configure(LunaHoldingObjectConfigure::class, function ($configure) {
    $configure
        // 自定义模型
        ->setUniqueObjectHoldingModel(CustomUniqueObjectHolding::class)
        ->setUniqueObjectHoldingChangeLogModel(CustomChangeLog::class)
        
        // 是否使用数据库唯一索引冲突处理
        ->setUseDbUniqueConflictHandling(true)
        
        // 是否启用变动日志
        ->setEnableChangeLog(true)
        
        // 默认持有状态
        ->setDefaultStatus(HoldingStatus::Normal)
        
        // 并发控制选项
        ->setUseCacheLock(true)              // 是否使用缓存原子锁
        ->setLockTimeout(10)                 // 原子锁超时时间（秒）
        ->setLockWaitTimeout(5)              // 原子锁等待时间（秒）
        
        // 存在性缓存选项
        ->setEnableExistenceCache(true)      // 是否启用存在性缓存
        ->setExistenceCacheTTL(300);         // 缓存有效期（秒），默认5分钟
});
```

配置属性都是公开可读的，但只能通过 setter 方法修改：
```php
// 读取配置
echo $configure->enableChangeLog;        // true
echo $configure->existenceCacheTTL;      // 300

// 修改配置（只能通过方法）
$configure->setEnableChangeLog(false);
```

### BusinessEvent 集成

HoldingObject 组件与 Foundation/BusinessEvent 组件集成，用于记录和展示操作历史。组件会自动注册一个名为 `holding` 的事件分组。

建议预先创建相关的业务事件：

```php
// 创建业务事件
luna_business_event()->createBusinessEvent(
    'holding.daily_checkin',        // 事件名称
    'holding',                      // 使用 holding 分组
    'checkin',                      // 处理器标识
    '{owner} 完成了每日签到',        // 消息格式化模板
    '每日签到'                      // 显示名称
);

luna_business_event()->createBusinessEvent(
    'holding.grant_chance',
    'holding',
    'lottery',
    '{owner} 通过 {source} 获得了 {quantity} 次抽奖机会',
    '获得抽奖机会'
);

luna_business_event()->createBusinessEvent(
    'holding.purchase_limit',
    'holding',
    'product',
    '{owner} 购买了商品 #{object_id}，数量：{quantity}',
    '商品限购记录'
);

luna_business_event()->createBusinessEvent(
    'holding.status_update',
    'holding',
    'status',
    '{owner} 的持有对象 {object_type}#{object_id} 状态更新为 {status}',
    '状态更新'
);

luna_business_event()->createBusinessEvent(
    'holding.delete',
    'holding',
    'delete',
    '{owner} 的持有对象 {object_type}#{object_id} 已删除',
    '删除持有'
);
```

### 并发控制策略

组件提供了两种并发控制策略：

1. **缓存原子锁**（推荐）
   - 使用 Laravel Cache 的原子锁机制
   - 在创建或更新持有记录前获取锁
   - 适用于分布式环境
   - 通过 `useCacheLock` 配置开启
   - 支持的缓存驱动：Memcached、Redis、DynamoDB、Database、File、Array
   - 如果缓存驱动不支持锁，会自动降级到非锁模式

2. **数据库唯一索引 + ON DUPLICATE KEY UPDATE**
   - 利用 MySQL 的唯一索引冲突处理
   - 当 `enableHoldMultiple` 为 true 时自动使用
   - 适用于需要累加数量的场景
   - 通过 `useDbUniqueConflictHandling` 配置开启

两种策略可以同时使用以获得最佳的并发安全性。

## 数据库迁移

运行以下命令发布迁移文件：

```bash
php artisan vendor:publish --tag=luna-holding-object-migrations
php artisan migrate
```

## 使用参数构造器

组件提供了 `UniqueHoldingParams` 参数构造器，让参数组织更加清晰。参数构造器的所有属性都是公开可读的，但只能通过链式方法设置：

### 方式一：使用 WithParams 方法

```php
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\UniqueHoldingParams;

// 创建签到记录
$params = unique_holding_params()
    ->owner(SessionHolder::fromModel($user))
    ->object('daily-checkin', 1)
    ->payload([
        'check_in_time' => now()->toDateTimeString(),
        'ip' => request()->ip(),
    ])
    ->event('holding.daily_checkin');
    
$holding = luna_holding_object()->createWithParams($params);

// 检查是否已签到
$params = unique_holding_params()
    ->owner(SessionHolder::fromModel($user))
    ->object('daily-checkin', 1)
    ->forceNoCache(); // 强制不使用缓存
    
$exists = luna_holding_object()->existsWithParams($params);

// 增加抽奖次数
$params = unique_holding_params()
    ->owner(SessionHolder::fromModel($user))
    ->object('lottery-chance', $lotteryId)
    ->quantity(3)
    ->with('source', 'daily_login')
    ->event('holding.grant_chance');
    
$holding = luna_holding_object()->increaseWithParams($params);

// 更新状态
$params = unique_holding_params()
    ->owner(SessionHolder::fromModel($user))
    ->object('daily-checkin', 1)
    ->status(HoldingStatus::Used)
    ->with('reward_claimed_at', now()->toDateTimeString())
    ->event('holding.claim_reward');
    
$holding = luna_holding_object()->updateStatusWithParams($params);
```

### 方式二：使用展开运算符

```php
// 使用 build 方法配合展开运算符
$params = unique_holding_params()
    ->owner(SessionHolder::fromModel($user))
    ->object('lottery-chance', $lotteryId)
    ->quantity(1)
    ->with('source', 'share')
    ->event('holding.grant_chance.share');
    
// 调用原始方法
$holding = luna_holding_object()->increaseUniqueHoldingQuantity(
    ...$params->buildForQuantityChange()
);

// 或者更简洁的写法
$holding = luna_holding_object()->createUniqueHolding(
    ...unique_holding_params()
        ->owner($owner)
        ->object('coupon', $couponCode)
        ->payload(['claimed_at' => now()])
        ->event('holding.claim_coupon')
        ->buildForCreate()
);
```

### 参数复用与属性访问

```php
// 创建基础参数
$baseParams = unique_holding_params()
    ->owner($owner)
    ->object('feature-access', 'premium');

// 直接访问属性（只读）
echo $baseParams->objectName; // 'feature-access'
echo $baseParams->objectId;   // 'premium'
echo $baseParams->quantity;   // 1.0 (默认值)

// 检查是否存在
if (!luna_holding_object()->existsWithParams($baseParams)) {
    // 基于基础参数创建新参数
    $createParams = clone $baseParams;
    $createParams->payload(['granted_at' => now()])
        ->event('holding.grant_feature');
        
    luna_holding_object()->createWithParams($createParams);
}
```

### UniqueHoldingParams 可用方法

- `owner(SessionHolder $owner)` - 设置持有者
- `object(string|int $objectName, string|int $objectId)` - 设置对象名称和ID
- `payload(array $payload)` - 设置载荷数据
- `with(string $key, mixed $value)` - 添加单个载荷数据
- `quantity(float $quantity)` - 设置数量
- `unit(int $unitId)` - 设置单位ID
- `event(int|string $eventId)` - 设置事件ID
- `status(HoldingStatus $status)` - 设置状态
- `forceNoCache(bool $force = true)` - 设置是否强制不使用缓存

### 构建方法

- `buildForCreate()` - 构建创建操作的参数数组
- `buildForGet()` - 构建获取操作的参数数组
- `buildForExists()` - 构建检查存在性的参数数组
- `buildForQuantityChange()` - 构建增减数量的参数数组
- `buildForStatusUpdate()` - 构建状态更新的参数数组
- `buildForDelete()` - 构建删除操作的参数数组

## 查询参数构造器

组件提供了 `HoldingQueryParams` 查询参数构造器，用于构建复杂查询：

### 可用方法

- `owner(SessionHolder $owner)` - 设置所有者
- `object(string|int $objectName, string|int $objectId = null)` - 设置对象名称和可选的ID
- `objectId(string|int $objectId)` - 单独设置对象ID
- `status(HoldingStatus|int $status)` - 设置状态过滤
- `normal()` - 只查询正常状态
- `active()` - 只查询活跃状态
- `quantityRange(?float $min, ?float $max)` - 设置数量范围
- `minQuantity(float $quantity)` - 设置最小数量
- `maxQuantity(float $quantity)` - 设置最大数量
- `orderBy(string $field, string $direction)` - 设置排序
- `latest()` - 按创建时间倒序
- `oldest()` - 按创建时间正序
- `limit(int $limit)` - 限制数量
- `offset(int $offset)` - 设置偏移量
- `page(int $page, int $perPage = 15)` - 分页设置

### 使用示例

```php
// 创建查询参数
$params = holding_query()
    ->owner($owner)
    ->object('coupon')
    ->normal()
    ->quantityRange(1, null)  // 至少有1个
    ->latest()
    ->page(1, 20);

// 获取查询结果
$results = luna_holding_object()->queryWithParams($params)->get();

// 或使用便捷方法
$holdings = luna_holding_object()->getOwnerHoldingsWithParams($params);
$count = luna_holding_object()->countWithParams($params);
$first = luna_holding_object()->firstWithParams($params);
```

## 批量操作

组件提供了 `BatchOperationParams` 批量操作参数构造器，用于批量处理多个持有对象：

### 批量创建

```php
// 批量为多个用户创建签到记录
$results = holding_batch()
    ->forCreate()
    ->defaultPayload(['source' => 'batch_import'])
    ->defaultEvent('holding.batch_create')
    ->addItem($user1, 'daily-checkin', 1)
    ->addItem($user2, 'daily-checkin', 1)
    ->addItem($user3, 'daily-checkin', 1)
    ->execute();

// 检查结果
foreach ($results as $index => $result) {
    if ($result['success']) {
        echo "用户 {$index} 签到成功\n";
    } else {
        echo "用户 {$index} 签到失败: {$result['error']}\n";
    }
}
```

### 批量发放奖励

```php
// 为活跃用户批量发放抽奖次数
$activeUsers = User::where('active', true)->limit(100)->get();

$batch = holding_batch()
    ->forIncrease()
    ->defaultPayload(['source' => 'activity_reward'])
    ->defaultEvent('holding.batch_grant')
    ->continueOnError() // 错误时继续处理其他项
    ->useTransaction(); // 使用事务

foreach ($activeUsers as $user) {
    $batch->addItem(
        SessionHolder::fromModel($user),
        'lottery-chance',
        2024,
        ['granted_at' => now()],
        5.0 // 每人5次
    );
}

$results = $batch->execute();
```

### 批量更新状态

```php
// 批量使优惠券过期
$expiredCoupons = [
    ['user' => $user1, 'coupon' => 'SUMMER2024'],
    ['user' => $user2, 'coupon' => 'SUMMER2024'],
    ['user' => $user3, 'coupon' => 'WELCOME50'],
];

$results = holding_batch()
    ->forUpdateStatus(HoldingStatus::Expired)
    ->defaultPayload(['expired_reason' => 'promotion_ended'])
    ->defaultEvent('holding.batch_expire')
    ->continueOnError(false) // 遇到错误就停止
    ->addItemsForOwner(
        SessionHolder::fromModel($admin),
        array_map(fn($item) => [
            'name' => 'coupon',
            'id' => $item['coupon']
        ], $expiredCoupons)
    )
    ->execute();
```

### 批量操作同一用户的多个对象

```php
// 新用户注册礼包
$newUserGifts = [
    ['name' => 'coupon', 'id' => 'NEWUSER20', 'payload' => ['discount' => 20]],
    ['name' => 'lottery-chance', 'id' => 2024, 'quantity' => 10],
    ['name' => 'vip-trial', 'id' => 'TRIAL7D', 'payload' => ['days' => 7]],
];

$results = holding_batch()
    ->forCreate()
    ->defaultEvent('holding.new_user_gift')
    ->useTransaction() // 确保原子性
    ->addItemsForOwner(
        SessionHolder::fromModel($newUser),
        $newUserGifts
    )
    ->execute();

// 所有礼包必须全部发放成功
$allSuccess = collect($results)->every('success');
if (!$allSuccess) {
    // 处理失败情况...
}
```

## 最佳实践

1. **合理设计对象ID**：对象ID应该有业务含义，如签到使用固定ID配合定期清理，限购使用商品ID等
2. **利用载荷数据**：payload 字段可以存储任意相关数据，充分利用以减少额外查询
3. **状态管理**：合理使用预定义状态，必要时可以扩展状态常量
4. **日志记录**：启用变动日志以便追踪历史记录和数据分析
5. **权限控制**：在 `permit` 方法中实现细粒度的权限控制
6. **事务处理**：组件内部已实现事务，确保数据一致性
7. **性能优化**：
   - 对于高频检查场景，启用存在性缓存
   - 使用 `forceNoCache` 参数获取最新数据
   - 合理设置缓存 TTL 避免数据不一致
8. **并发处理**：
   - 使用 `increaseUniqueHoldingQuantity` 和 `decreaseUniqueHoldingQuantity` 进行原子操作
   - 启用缓存锁避免并发创建问题