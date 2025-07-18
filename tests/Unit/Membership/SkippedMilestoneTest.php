<?php

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\NumericCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneConfiguration;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneLog;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试用的里程碑处理器
class SkippableMilestoneHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '可跳级里程碑处理器';
    }

    public function handlerDescription(): string
    {
        return '用于测试跳级记录的里程碑处理器';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('level1', '等级1', 1, ['required_points' => 100]),
            new MilestoneLevel('level2', '等级2', 2, ['required_points' => 500]),
            new MilestoneLevel('level3', '等级3', 3, ['required_points' => 1000]),
            new MilestoneLevel('level4', '等级4', 4, ['required_points' => 5000]),
            new MilestoneLevel('level5', '等级5', 5, ['required_points' => 10000]),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'level1' => [new NumericCondition('points', '>=', 100)],
            'level2' => [new NumericCondition('points', '>=', 500)],
            'level3' => [new NumericCondition('points', '>=', 1000)],
            'level4' => [new NumericCondition('points', '>=', 5000)],
            'level5' => [new NumericCondition('points', '>=', 10000)],
            default => [],
        };
    }
}

// 测试用户
class TestOwner implements SessionHolder
{
    public function __construct(
        public int $id = 1,
        public int $points = 0
    ) {
    }
    
    public function getOperatorTypeName(): string
    {
        return 'test_owner';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('test_owner');
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return ['points' => $this->points];
    }
}

beforeEach(function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 2000,
        'name' => SkippableMilestoneHandler::class,
        'group_id' => 1,
        'display_name' => '可跳级里程碑处理器',
        'description' => '测试用',
        'handler' => SkippableMilestoneHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型
    $this->milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'skippable_milestone',
        'display_name' => '可跳级里程碑',
        'description' => '用于测试跳级记录',
        'handler_id' => 2000,
        'config' => [],
    ]);
    
    $this->handler = new SkippableMilestoneHandler();
    $this->handler->setMilestoneType($this->milestoneType);
});

test('记录历史是强制的', function () {
    $config = new MilestoneConfiguration([
        'record_history' => false, // 尝试设置为 false
    ]);
    
    // 但实际上应该返回 true
    expect($config->recordHistory())->toBeTrue();
});

test('直接达到更高等级时记录中间的里程碑', function () {
    $config = new MilestoneConfiguration([
        'record_skipped_milestones' => true,
    ]);
    $this->handler->withConfig($config);
    
    $owner = new TestOwner(1, 6000); // 积分足够达到 level4
    
    // 用户从无等级直接到 level4
    $level = $this->handler->trigger($owner, ['points' => 6000]);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('level4');
    
    // 检查里程碑日志
    $logs = MembershipMilestoneLog::query()
        ->where('owner_id', 1)
        ->orderBy('id')
        ->get();
    
    // 应该有4条记录：level1, level2, level3, level4
    expect($logs)->toHaveCount(4);
    
    // 检查记录顺序
    expect($logs[0]->milestone)->toBe(hash_code('level1'));
    expect($logs[0]->payload['skipped'])->toBeTrue();
    
    expect($logs[1]->milestone)->toBe(hash_code('level2'));
    expect($logs[1]->payload['skipped'])->toBeTrue();
    expect($logs[1]->before_milestone)->toBe(hash_code('level1'));
    
    expect($logs[2]->milestone)->toBe(hash_code('level3'));
    expect($logs[2]->payload['skipped'])->toBeTrue();
    expect($logs[2]->before_milestone)->toBe(hash_code('level2'));
    
    expect($logs[3]->milestone)->toBe(hash_code('level4'));
    expect($logs[3]->payload['skipped'] ?? false)->toBeFalse();
    expect($logs[3]->before_milestone)->toBe(hash_code('level3'));
});

test('配置不记录跳过的里程碑', function () {
    $config = new MilestoneConfiguration([
        'record_skipped_milestones' => false,
    ]);
    $this->handler->withConfig($config);
    
    $owner = new TestOwner(2, 6000); // 积分足够达到 level4
    
    // 用户从无等级直接到 level4
    $level = $this->handler->trigger($owner, ['points' => 6000]);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('level4');
    
    // 检查里程碑日志
    $logs = MembershipMilestoneLog::query()
        ->where('owner_id', 2)
        ->orderBy('id')
        ->get();
    
    // 只有1条记录：level4
    expect($logs)->toHaveCount(1);
    expect($logs[0]->milestone)->toBe(hash_code('level4'));
    expect($logs[0]->before_milestone)->toBeNull();
});

test('从已有等级升级时记录中间里程碑', function () {
    $config = new MilestoneConfiguration([
        'record_skipped_milestones' => true,
    ]);
    $this->handler->withConfig($config);
    
    $owner = new TestOwner(3, 200); // 先达到 level1
    
    // 先设置为 level1
    $level1 = collect($this->handler->getMilestoneLevels())->firstWhere('identifier', 'level1');
    $this->handler->updateMilestone($owner, $level1);
    
    // 现在升级到 level4
    $owner->points = 6000;
    $level = $this->handler->trigger($owner, ['points' => 6000]);
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('level4');
    
    // 检查里程碑日志（不包括初始的 level1）
    $logs = MembershipMilestoneLog::query()
        ->where('owner_id', 3)
        ->where('milestone', '!=', hash_code('level1'))
        ->orderBy('id')
        ->get();
    
    // 应该有3条记录：level2, level3, level4
    expect($logs)->toHaveCount(3);
    
    expect($logs[0]->milestone)->toBe(hash_code('level2'));
    expect($logs[0]->payload['skipped'])->toBeTrue();
    expect($logs[0]->before_milestone)->toBe(hash_code('level1'));
    
    expect($logs[1]->milestone)->toBe(hash_code('level3'));
    expect($logs[1]->payload['skipped'])->toBeTrue();
    expect($logs[1]->before_milestone)->toBe(hash_code('level2'));
    
    expect($logs[2]->milestone)->toBe(hash_code('level4'));
    expect($logs[2]->payload['skipped'] ?? false)->toBeFalse();
    expect($logs[2]->before_milestone)->toBe(hash_code('level3'));
});

test('只记录满足条件的中间里程碑', function () {
    $config = new MilestoneConfiguration([
        'record_skipped_milestones' => true,
    ]);
    $this->handler->withConfig($config);
    
    $owner = new TestOwner(4, 700); // 积分只够达到 level2，但我们强制设置到 level4
    
    // 模拟特殊情况：虽然积分不够，但通过其他途径获得 level4
    $level4 = collect($this->handler->getMilestoneLevels())->firstWhere('identifier', 'level4');
    $this->handler->updateMilestone($owner, $level4, ['special_promotion' => true]);
    
    // 检查里程碑日志
    $logs = MembershipMilestoneLog::query()
        ->where('owner_id', 4)
        ->orderBy('id')
        ->get();
    
    // 应该有3条记录：level1, level2, level4（level3 不满足条件所以没记录）
    expect($logs)->toHaveCount(3);
    
    expect($logs[0]->milestone)->toBe(hash_code('level1'));
    expect($logs[0]->payload['skipped'])->toBeTrue();
    
    expect($logs[1]->milestone)->toBe(hash_code('level2'));
    expect($logs[1]->payload['skipped'])->toBeTrue();
    
    expect($logs[2]->milestone)->toBe(hash_code('level4'));
    expect($logs[2]->payload['special_promotion'])->toBeTrue();
});