<?php

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembership;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\NumericCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试处理器
class RefactoredTestHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '重构测试处理器';
    }

    public function handlerDescription(): string
    {
        return '用于测试重构后的里程碑系统';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('level1', 'Level 1', 1),
            new MilestoneLevel('level2', 'Level 2', 2),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'level1' => [new NumericCondition('score', '>=', 100)],
            'level2' => [new NumericCondition('score', '>=', 500)],
            default => [],
        };
    }
}

// 测试用户
class RefactoredTestUser implements SessionHolder
{
    public function __construct(
        public int $id = 1,
        public int $score = 0
    ) {}
    
    public function getOperatorTypeName(): string
    {
        return 'refactored_test_user';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('refactored_test_user');
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return ['score' => $this->score];
    }
}

it('访问里程碑功能 - 默认启用', function () {
    $membership = luna_membership();
    
    expect($membership->isMilestoneEnabled())->toBeTrue();
    expect($membership->milestone())->not->toBeNull();
});

it('禁用里程碑功能', function () {
    // 创建禁用里程碑的配置
    $config = LunaMembershipConfigure::create()
        ->withoutMilestone()
        ->build();
    
    // 创建会员系统实例
    $membership = new LunaMembership(
        $config,
        app('cache.store'),
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
    );
    
    expect($membership->isMilestoneEnabled())->toBeFalse();
    expect($membership->milestone())->toBeNull();
});

it('通过 milestone() 方法创建里程碑类型', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 5000,
        'name' => RefactoredTestHandler::class,
        'group_id' => 1,
        'display_name' => '重构测试处理器',
        'description' => '测试用',
        'handler' => RefactoredTestHandler::class,
        'config' => [],
    ]);
    
    $membership = luna_membership();
    $milestone = $membership->milestone();
    
    expect($milestone)->not->toBeNull();
    
    // 创建里程碑类型
    $type = $milestone->createType('refactored_test', RefactoredTestHandler::class, [
        'display_name' => '重构测试里程碑',
        'description' => '测试重构后的系统',
    ]);
    
    expect($type)->toBeInstanceOf(MembershipMilestoneType::class);
    expect($type->name)->toBe('refactored_test');
});

it('通过 milestone() 方法触发里程碑评估', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 5001,
        'name' => RefactoredTestHandler::class,
        'group_id' => 1,
        'display_name' => '重构测试处理器',
        'description' => '测试用',
        'handler' => RefactoredTestHandler::class,
        'config' => [],
    ]);
    
    $membership = luna_membership();
    $milestone = $membership->milestone();
    
    // 创建里程碑类型
    $milestone->createType('refactored_trigger', RefactoredTestHandler::class);
    
    // 创建用户并触发评估
    $user = new RefactoredTestUser(1, 600);
    $level = $milestone->trigger($user, 'refactored_trigger');
    
    expect($level)->not->toBeNull();
    expect($level->identifier)->toBe('level2');
});

it('通过 milestone() 方法获取当前里程碑', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 5002,
        'name' => RefactoredTestHandler::class,
        'group_id' => 1,
        'display_name' => '重构测试处理器',
        'description' => '测试用',
        'handler' => RefactoredTestHandler::class,
        'config' => [],
    ]);
    
    $membership = luna_membership();
    $milestone = $membership->milestone();
    
    // 创建里程碑类型
    $milestone->createType('refactored_current', RefactoredTestHandler::class);
    
    // 创建用户并设置里程碑
    $user = new RefactoredTestUser(1, 600);
    $milestone->trigger($user, 'refactored_current');
    
    // 获取当前里程碑
    $current = $milestone->getCurrent($user, 'refactored_current');
    
    expect($current)->not->toBeNull();
    expect($current->identifier)->toBe('level2');
});

it('通过 milestone() 方法获取里程碑统计', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 5003,
        'name' => RefactoredTestHandler::class,
        'group_id' => 1,
        'display_name' => '重构测试处理器',
        'description' => '测试用',
        'handler' => RefactoredTestHandler::class,
        'config' => [],
    ]);
    
    $membership = luna_membership();
    $milestone = $membership->milestone();
    
    // 创建里程碑类型
    $milestone->createType('refactored_stats', RefactoredTestHandler::class);
    
    // 创建多个用户并设置不同里程碑
    $milestone->trigger(new RefactoredTestUser(1, 150), 'refactored_stats');
    $milestone->trigger(new RefactoredTestUser(2, 150), 'refactored_stats');
    $milestone->trigger(new RefactoredTestUser(3, 600), 'refactored_stats');
    
    // 获取统计信息
    $stats = $milestone->getStatistics('refactored_stats');
    
    expect($stats)->toHaveCount(2);
    expect($stats['level1']['count'])->toBe(2);
    expect($stats['level2']['count'])->toBe(1);
});