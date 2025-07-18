<?php

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\NumericCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneConfiguration;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneLog;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试用的里程碑处理器
class TestMilestoneHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '测试里程碑处理器';
    }

    public function handlerDescription(): string
    {
        return '用于测试的里程碑处理器';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('bronze', '青铜会员', 1, ['icon' => 'bronze.png']),
            new MilestoneLevel('silver', '白银会员', 2, ['icon' => 'silver.png']),
            new MilestoneLevel('gold', '黄金会员', 3, ['icon' => 'gold.png']),
            new MilestoneLevel('platinum', '铂金会员', 4, ['icon' => 'platinum.png']),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'bronze' => [
                new NumericCondition('points', '>=', 100),
            ],
            'silver' => [
                new NumericCondition('points', '>=', 500),
                new NumericCondition('purchases', '>=', 5),
            ],
            'gold' => [
                new NumericCondition('points', '>=', 1000),
                new NumericCondition('purchases', '>=', 10),
            ],
            'platinum' => [
                new NumericCondition('points', '>=', 5000),
                new NumericCondition('purchases', '>=', 20),
            ],
            default => [],
        };
    }
}

// 测试用的用户模型
class TestUser implements SessionHolder
{
    public function __construct(
        public int $id,
        public int $points = 0,
        public int $purchases = 0
    ) {
    }
    
    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('test_user');
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return [
            'points' => $this->points,
            'purchases' => $this->purchases,
        ];
    }
}

beforeEach(function () {
    // 创建处理器记录
    $handler = Handler::query()->forceCreate([
        'id' => 999,
        'name' => TestMilestoneHandler::class,
        'group_id' => 1,
        'display_name' => '测试里程碑处理器',
        'description' => '测试用',
        'handler' => TestMilestoneHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型
    $this->milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'test_milestone',
        'display_name' => '测试里程碑',
        'description' => '用于测试的里程碑类型',
        'handler_id' => 999,
        'config' => [],
    ]);
    
    $this->handler = new TestMilestoneHandler();
    $this->handler->setMilestoneType($this->milestoneType);
});

test('里程碑等级定义', function () {
    $levels = $this->handler->getMilestoneLevels();
    
    expect($levels)->toHaveCount(4);
    expect($levels[0]->identifier)->toBe('bronze');
    expect($levels[0]->displayName)->toBe('青铜会员');
    expect($levels[0]->sequence)->toBe(1);
    expect($levels[0]->getMeta('icon'))->toBe('bronze.png');
});

test('里程碑条件获取', function () {
    $conditions = $this->handler->getMilestoneConditions('silver');
    
    expect($conditions)->toHaveCount(2);
    expect($conditions[0])->toBeInstanceOf(NumericCondition::class);
    expect($conditions[0]->getConfig()['field'])->toBe('points');
    expect($conditions[0]->getConfig()['value'])->toBe(500.0);
});

test('里程碑评估 - 不满足任何条件', function () {
    $user = new TestUser(1, points: 50, purchases: 0);
    
    $level = $this->handler->evaluate($user);
    
    expect($level)->toBeNull();
});

test('里程碑评估 - 达到青铜等级', function () {
    $user = new TestUser(1, points: 150, purchases: 2);
    
    $level = $this->handler->evaluate($user);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('bronze');
});

test('里程碑评估 - 达到白银等级', function () {
    $user = new TestUser(1, points: 600, purchases: 6);
    
    $level = $this->handler->evaluate($user);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('silver');
});

test('里程碑评估 - 从高到低评估', function () {
    $user = new TestUser(1, points: 5500, purchases: 25);
    
    $level = $this->handler->evaluate($user);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('platinum');
});

test('里程碑更新', function () {
    // 为了保持原有测试行为，禁用跳过里程碑记录
    $config = new MilestoneConfiguration([
        'record_skipped_milestones' => false,
    ]);
    $this->handler->withConfig($config);
    
    $user = new TestUser(1, points: 600, purchases: 6);
    $level = new MilestoneLevel('silver', '白银会员', 2);
    
    $milestone = $this->handler->updateMilestone($user, $level, ['reason' => 'manual']);
    
    expect($milestone)->toBeInstanceOf(MembershipMilestone::class);
    expect($milestone->owner_id)->toBe(1);
    expect($milestone->owner_type)->toBe(hash_code('test_user'));
    expect($milestone->milestone)->toBe(hash_code('silver'));
    expect($milestone->payload['reason'])->toBe('manual');
    
    // 检查日志
    $log = MembershipMilestoneLog::query()->first();
    expect($log)->not->toBeNull();
    expect($log->milestone)->toBe(hash_code('silver'));
    expect($log->before_milestone)->toBeNull();
});

test('里程碑升级记录', function () {
    $user = new TestUser(1);
    
    // 先设置为青铜
    $bronze = new MilestoneLevel('bronze', '青铜会员', 1);
    $this->handler->updateMilestone($user, $bronze);
    
    // 升级到白银
    $silver = new MilestoneLevel('silver', '白银会员', 2);
    $this->handler->updateMilestone($user, $silver);
    
    $logs = MembershipMilestoneLog::query()->orderBy('id')->get();
    expect($logs)->toHaveCount(2);
    expect($logs[1]->before_milestone)->toBe(hash_code('bronze'));
    expect($logs[1]->milestone)->toBe(hash_code('silver'));
});

test('获取当前里程碑', function () {
    $user = new TestUser(1);
    $level = new MilestoneLevel('gold', '黄金会员', 3);
    
    $this->handler->updateMilestone($user, $level);
    
    $current = $this->handler->getCurrentMilestone($user);
    expect($current)->not->toBeNull();
    expect($current->identifier)->toBe('gold');
});

test('检查历史里程碑', function () {
    $user = new TestUser(1);
    
    // 创建一些历史记录
    $levels = ['bronze', 'silver', 'gold'];
    foreach ($levels as $levelId) {
        $level = collect($this->handler->getMilestoneLevels())
            ->firstWhere('identifier', $levelId);
        $this->handler->updateMilestone($user, $level);
    }
    
    expect($this->handler->hasReachedMilestone($user, 'silver'))->toBeTrue();
    expect($this->handler->hasReachedMilestone($user, 'platinum'))->toBeFalse();
});

test('里程碑触发', function () {
    $user = new TestUser(1, points: 1200, purchases: 12);
    
    $level = $this->handler->trigger($user);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('gold');
    
    // 验证已保存
    $milestone = MembershipMilestone::query()->first();
    expect($milestone)->not->toBeNull();
    expect($milestone->milestone)->toBe(hash_code('gold'));
});

test('配置 - 不允许降级', function () {
    $config = new MilestoneConfiguration([
        'allow_downgrade' => false,
    ]);
    $this->handler->withConfig($config);
    
    $user = new TestUser(1);
    
    // 先设置为黄金
    $gold = new MilestoneLevel('gold', '黄金会员', 3);
    $this->handler->updateMilestone($user, $gold);
    
    // 条件不再满足黄金，但不允许降级
    $user->points = 600;
    $user->purchases = 6;
    
    $level = $this->handler->evaluate($user);
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('gold'); // 保持黄金等级
});

test('配置 - 允许降级', function () {
    $config = new MilestoneConfiguration([
        'allow_downgrade' => true,
    ]);
    $this->handler->withConfig($config);
    
    $user = new TestUser(1);
    
    // 先设置为黄金
    $gold = new MilestoneLevel('gold', '黄金会员', 3);
    $this->handler->updateMilestone($user, $gold);
    
    // 条件只满足白银
    $user->points = 600;
    $user->purchases = 6;
    
    $level = $this->handler->evaluate($user);
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('silver'); // 降级到白银
});

test('条件策略 - all', function () {
    $config = new MilestoneConfiguration([
        'condition_strategy' => 'all',
    ]);
    $this->handler->withConfig($config);
    
    $user = new TestUser(1, points: 600, purchases: 3); // 积分够但购买次数不够
    
    $level = $this->handler->evaluate($user);
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('bronze'); // 只能达到青铜
});

test('获取里程碑历史', function () {
    $user = new TestUser(1);
    
    // 创建一些历史
    foreach (['bronze', 'silver', 'gold'] as $levelId) {
        $level = collect($this->handler->getMilestoneLevels())
            ->firstWhere('identifier', $levelId);
        $this->handler->updateMilestone($user, $level);
        sleep(1); // 确保时间不同
    }
    
    $history = $this->handler->getMilestoneHistory($user, 2);
    expect($history)->toHaveCount(2);
    expect($history[0]->milestone)->toBe(hash_code('gold'));
    expect($history[1]->milestone)->toBe(hash_code('silver'));
});