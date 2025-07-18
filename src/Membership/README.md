# Luna Membership 会员体系模块

Luna Membership 是一个灵活的会员体系框架，提供了会员等级（里程碑）管理的完整解决方案。

## 核心概念

### 里程碑（Milestone）
里程碑是会员体系中的核心概念，代表会员可以达到的不同等级或成就。每个里程碑都有：
- **标识符（identifier）**：唯一标识
- **显示名称（displayName）**：用户友好的名称
- **顺序（sequence）**：用于排序和比较等级高低
- **元数据（metadata）**：额外信息如图标、描述等

### 里程碑类型（Milestone Type）
一个里程碑类型代表一套完整的里程碑体系，如 VIP 等级、成就系统等。每个类型都有自己的处理器来定义等级和条件。

### 里程碑条件（Milestone Condition）
定义达到某个里程碑需要满足的条件，支持多种条件组合。

## 快速开始

### 1. 创建里程碑处理器

```php
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\NumericCondition;

class VipMilestoneHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return 'VIP会员等级';
    }

    public function handlerDescription(): string
    {
        return '基于消费金额的VIP会员等级体系';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('regular', '普通会员', 1, [
                'icon' => 'regular.png',
                'discount' => 0,
            ]),
            new MilestoneLevel('silver', '白银会员', 2, [
                'icon' => 'silver.png',
                'discount' => 5,
            ]),
            new MilestoneLevel('gold', '黄金会员', 3, [
                'icon' => 'gold.png',
                'discount' => 10,
            ]),
            new MilestoneLevel('platinum', '铂金会员', 4, [
                'icon' => 'platinum.png',
                'discount' => 15,
            ]),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'regular' => [], // 无条件
            'silver' => [
                new NumericCondition('total_spent', '>=', 1000),
            ],
            'gold' => [
                new NumericCondition('total_spent', '>=', 5000),
                new NumericCondition('order_count', '>=', 10),
            ],
            'platinum' => [
                new NumericCondition('total_spent', '>=', 10000),
                new NumericCondition('order_count', '>=', 20),
            ],
            default => [],
        };
    }
}
```

### 2. 注册里程碑处理器

会员模块会自动注册 `membership-milestones` 处理器组，你只需要在 `AppServiceProvider` 中添加自己的处理器：

```php
public function customRegister(): void
{
    // 注册会员模块
    $this->registerModule(
        LunaMembershipConfigure::create()->build()
    );
    
    // 扩展里程碑处理器组（注意：使用短横线而非下划线）
    $this->extendModule(function() {
        return LunaHandlerConfigure::create()
            ->group('membership-milestones', '会员里程碑', function($register) {
                $register->handler(VipMilestoneHandler::class);
                $register->handler(AchievementMilestoneHandler::class);
            })
            ->build();
    });
}
```

### 3. 创建里程碑类型

```php
$membership = luna_membership();

// 创建 VIP 等级里程碑类型
$membership->milestone()->createType(
    'vip_level',
    VipMilestoneHandler::class,
    [
        'display_name' => 'VIP等级',
        'description' => '基于消费金额的会员等级',
        'config' => [
            'allow_downgrade' => false, // 不允许降级
            'auto_upgrade' => true,     // 自动升级
            'record_history' => true,   // 记录历史
        ],
    ]
);
```

#### 自动创建初始里程碑

当创建新的里程碑类型时，系统会自动为所有已绑定的用户检查并创建初始里程碑：

- 如果初始等级没有条件限制，所有用户都会获得该初始等级
- 如果初始等级有条件限制，只有满足条件的用户会获得对应的里程碑
- 系统会根据用户的实际情况评估最合适的里程碑等级

如果不想自动创建初始里程碑，可以在创建时禁用：

```php
$membership->milestone()->createType(
    'vip_level',
    VipMilestoneHandler::class,
    $attributes,
    false // 禁用自动创建初始里程碑
);
```

### 4. 触发里程碑评估

```php
// 当用户完成订单后
$user->total_spent += $order->amount;
$user->order_count += 1;
$user->save();

// 触发里程碑评估
$newLevel = luna_membership()->milestone()->trigger($user, 'vip_level');

if ($newLevel) {
    // 发送升级通知
    $user->notify(new MilestoneReachedNotification($newLevel));
}
```

### 5. 查询里程碑信息

```php
// 获取用户当前的 VIP 等级
$currentLevel = luna_membership()->milestone()->getCurrent($user, 'vip_level');

// 获取用户所有里程碑
$allMilestones = luna_membership()->milestone()->getCurrent($user);

// 获取里程碑历史
$history = luna_membership()->milestone()->getHistory($user, 'vip_level', 10);

// 检查是否曾经达到过某个等级
$hasBeenGold = luna_membership()->milestone()->hasReached($user, 'vip_level', 'gold');
```

## 高级功能

### 模型替换

如果需要使用自定义的模型类，可以在配置时进行替换：

```php
public function customRegister(): void
{
    $this->registerModule(
        LunaMembershipConfigure::create()
            ->useMilestoneTypeModel(CustomMilestoneType::class)
            ->useMilestoneModel(CustomMilestone::class)
            ->useMilestoneLogModel(CustomMilestoneLog::class)
            ->build()
    );
}
```

自定义模型类需要继承原有的模型类，以保持兼容性。

### 里程碑等级配置覆盖

里程碑等级**默认支持**通过配置进行灵活覆盖，满足在线编辑需求：

```php
// 在 MembershipMilestoneType 的 config 中设置覆盖
$milestoneType = MembershipMilestoneType::query()->create([
    'name' => 'vip_milestone',
    'display_name' => 'VIP里程碑',
    'handler_id' => $handlerId,
    'config' => [
        'level_overrides' => [
            'gold' => [
                'display_name' => '黄金VIP会员',  // 覆盖显示名称
                'sequence' => 5,                  // 覆盖顺序
                'metadata' => [                   // 覆盖元数据
                    'icon' => 'gold_vip.png',
                    'description' => 'VIP专属黄金会员'
                ]
            ]
        ]
    ]
]);
```

覆盖规则：
- **配置覆盖默认启用**，所有获取里程碑等级的方法都会自动应用覆盖
- `identifier` 是核心标识符，不可覆盖
- `display_name`、`sequence` 和 `metadata` 都可以通过配置覆盖
- 元数据覆盖时会与原始元数据合并，新值会覆盖旧值

如果需要禁用配置覆盖，可以在处理器中重写方法：

```php
class StrictMilestoneHandler extends MemberMilestoneHandler
{
    // ... 其他方法
    
    protected function enableConfigOverrides(): bool
    {
        return false; // 禁用配置覆盖
    }
}
```

使用场景：
- 多语言支持：不同语言环境使用不同的显示名称
- 个性化定制：为不同客户定制专属的会员等级名称和图标
- A/B 测试：测试不同的等级顺序和名称对用户的影响
- 季节性活动：临时修改等级显示和图标适应节日主题

```php
// 创建里程碑等级的其他方式
// 1. 从字符串创建（简化版）
$level = MilestoneLevel::fromConfig('bronze', 1);

// 2. 从配置数组创建
$level = MilestoneLevel::fromConfig([
    'identifier' => 'bronze',
    'display_name' => '青铜会员',
    'sequence' => 1,
    'metadata' => ['icon' => 'bronze.png']
]);

// 3. 使用覆盖创建新实例
$originalLevel = new MilestoneLevel('gold', 'Gold Member', 3);
$customizedLevel = $originalLevel->withOverrides([
    'display_name' => '黄金贵宾',
    'metadata' => ['vip' => true]
]);
```

### 自定义条件

创建自定义条件类：

```php
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;

class ConsecutiveDaysCondition implements MilestoneCondition
{
    public function __construct(
        protected int $days,
        protected string $action = 'login'
    ) {}

    public function isSatisfied(mixed $owner, array $context = []): bool
    {
        // 检查连续天数逻辑
        return $owner->getConsecutiveDays($this->action) >= $this->days;
    }

    public function getIdentifier(): string
    {
        return "consecutive_{$this->action}_{$this->days}";
    }

    public function getDescription(): string
    {
        return "连续{$this->days}天{$this->action}";
    }

    public function getConfig(): array
    {
        return [
            'days' => $this->days,
            'action' => $this->action,
        ];
    }
}
```

### 使用预定义条件

框架提供了几个常用的条件实现：

```php
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\NumericCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\DataProviderCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\CommonDataProviders;

// 数值条件（从上下文获取数据）
new NumericCondition('points', '>=', 1000);

// 使用数据提供者条件（从数据库或其他数据源获取数据）
$consumptionProvider = CommonDataProviders::userTotalConsumption('orders', 'total_amount');
new DataProviderCondition($consumptionProvider, '>=', 10000);
```

### 使用数据提供者

数据提供者允许从各种数据源灵活获取数据，避免过度依赖模型：

```php
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\CommonDataProviders;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\CallbackDataProvider;

// 1. 使用预定义的数据提供者
$totalConsumption = CommonDataProviders::userTotalConsumption('orders', 'amount');
$orderCount = CommonDataProviders::userOrderCount('orders');
$monthlyConsumption = CommonDataProviders::monthlyConsumption('orders', 'amount', 3);

// 2. 创建自定义数据提供者
$customProvider = new CallbackDataProvider(
    'custom_metric',
    function (SessionHolder $owner, array $params) {
        // 从任意数据源获取数据
        $result = DB::table('custom_table')
            ->where('user_id', $owner->getOperatorId())
            ->where('type', $params['type'] ?? 'default')
            ->sum('value');
        
        return $result ?? 0;
    }
);

// 3. 缓存数据提供者
$cachedProvider = CommonDataProviders::cached(
    'expensive_calculation',
    function ($owner, $params) {
        // 执行复杂计算
        return $this->calculateComplexMetric($owner);
    },
    3600 // 缓存1小时
);

// 4. 组合多个数据提供者
$combinedProvider = CommonDataProviders::combined(
    'combined_score',
    [
        'consumption' => $totalConsumption,
        'orders' => $orderCount,
        'loyalty' => $loyaltyProvider,
    ],
    function (array $results) {
        // 基于多个指标计算综合得分
        return $results['consumption'] * 0.5 + 
               $results['orders'] * 30 + 
               $results['loyalty'] * 100;
    }
);
```

### 在里程碑处理器中注册数据提供者

```php
class AdvancedMilestoneHandler extends MemberMilestoneHandler
{
    protected function registerDataProviders($registry): void
    {
        // 注册用户消费数据提供者
        $registry->register(
            CommonDataProviders::userTotalConsumption('orders', 'total_amount')
        );
        
        // 注册团队数据提供者
        $registry->register(
            CommonDataProviders::teamTotalConsumption(
                function ($owner) {
                    return Team::where('leader_id', $owner->getOperatorId())
                        ->pluck('member_id')
                        ->toArray();
                },
                'orders',
                'total_amount'
            )
        );
        
        // 注册自定义业务指标
        $registry->register(new CallbackDataProvider(
            'activity_score',
            function ($owner, $params) {
                // 计算用户活跃度得分
                $loginDays = DB::table('user_logins')
                    ->where('user_id', $owner->getOperatorId())
                    ->where('created_at', '>=', now()->subDays(30))
                    ->distinct('date')
                    ->count();
                
                $interactions = DB::table('user_interactions')
                    ->where('user_id', $owner->getOperatorId())
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count();
                
                return $loginDays * 10 + $interactions * 2;
            }
        ));
    }
    
    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        $registry = $this->getConfig()->getDataProviderRegistry();
        $this->registerDataProviders($registry);
        
        return match ($milestoneIdentifier) {
            'active_member' => [
                new DataProviderCondition(
                    $registry->get('activity_score'),
                    '>=',
                    100,
                    'active_score',
                    '活跃度得分>=100'
                ),
            ],
            'team_leader' => [
                new DataProviderCondition(
                    $registry->get('team_total_consumption'),
                    '>=',
                    50000,
                    'team_consumption',
                    '团队消费>=50000'
                ),
            ],
            default => [],
        };
    }
}
```

### 配置选项

里程碑处理器支持多种配置：

```php
$config = [
    // 是否允许降级
    'allow_downgrade' => false,
    
    // 是否自动升级
    'auto_upgrade' => true,
    
    // 重新评估周期（秒），null 表示不自动重新评估
    'reevaluation_period' => 86400, // 每天
    
    // 条件策略：'all' 所有条件都满足，'any' 任意条件满足
    'condition_strategy' => 'all',
    
    // 是否记录历史
    'record_history' => true,
    
    // 自定义配置
    'custom' => [
        'special_rules' => true,
    ],
];
```

### 批量操作

```php
// 批量触发多个里程碑类型
$results = luna_membership()->milestone()->triggerMultiple(
    $user,
    ['vip_level', 'achievement_badges', 'loyalty_tier']
);

// 触发所有里程碑类型
$allResults = luna_membership()->milestone()->triggerAll($user);
```

### 统计信息

```php
// 获取里程碑分布统计
$stats = luna_membership()->milestone()->getStatistics('vip_level');

// 返回格式
[
    'regular' => [
        'level' => MilestoneLevel,
        'count' => 1500,
    ],
    'silver' => [
        'level' => MilestoneLevel,
        'count' => 800,
    ],
    // ...
]
```

## 最佳实践

1. **合理设计里程碑等级**
   - 等级之间应有明确的递进关系
   - 条件设置要合理，避免过难或过易
   - 考虑业务发展预留扩展空间

2. **性能优化**
   - 使用缓存减少数据库查询
   - 批量操作时使用 `triggerMultiple`
   - 合理设置重新评估周期

3. **用户体验**
   - 及时通知用户里程碑变化
   - 提供清晰的升级条件说明
   - 展示进度信息增强参与感

4. **数据一致性**
   - 使用事务确保数据一致性
   - 定期检查和修复数据异常
   - 保留完整的变更历史

## 扩展示例

### 成就系统

```php
class AchievementMilestoneHandler extends MemberMilestoneHandler
{
    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('first_purchase', '首次购买', 1, [
                'icon' => 'achievement/first_purchase.png',
                'points' => 10,
            ]),
            new MilestoneLevel('big_spender', '大手笔', 2, [
                'icon' => 'achievement/big_spender.png',
                'points' => 50,
            ]),
            // ...
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'first_purchase' => [
                new NumericCondition('order_count', '>=', 1),
            ],
            'big_spender' => [
                new NumericCondition('max_order_amount', '>=', 1000),
            ],
            default => [],
        };
    }
}
```

### 季节性会员等级

```php
class SeasonalMilestoneHandler extends MemberMilestoneHandler
{
    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        $currentSeason = $this->getCurrentSeason();
        
        return match ($milestoneIdentifier) {
            'seasonal_bronze' => [
                new DateRangeCondition(
                    $currentSeason['start'],
                    $currentSeason['end']
                ),
                new NumericCondition('season_points', '>=', 100),
            ],
            // ...
        };
    }

    protected function getCurrentSeason(): array
    {
        // 返回当前赛季的开始和结束日期
    }
}
```

## API 参考

详细的 API 文档请参考各个类的 PHPDoc 注释。主要接口包括：

- `LunaMembership`: 会员系统主类
- `LunaMilestone`: 里程碑管理类（通过 `$membership->milestone()` 访问）
- `MemberMilestoneHandler`: 里程碑处理器基类
- `MilestoneLevel`: 里程碑等级定义
- `MilestoneCondition`: 里程碑条件接口
- `MilestoneConfiguration`: 里程碑配置类

### 禁用里程碑功能

如果不需要里程碑功能，可以在配置时禁用：

```php
public function customRegister(): void
{
    $this->registerModule(
        LunaMembershipConfigure::create()
            ->withoutMilestone() // 禁用里程碑功能
            ->build()
    );
}

// 使用时检查
$membership = luna_membership();
if ($membership->milestone()) {
    // 里程碑功能可用
    $membership->milestone()->trigger($user, 'vip_level');
} else {
    // 里程碑功能已禁用
}
```