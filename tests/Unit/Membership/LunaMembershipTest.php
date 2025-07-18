<?php

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembership;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// 测试用的里程碑处理器
class SimpleMilestoneHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '简单里程碑处理器';
    }

    public function handlerDescription(): string
    {
        return '简单的里程碑处理器';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('level1', '等级1', 1),
            new MilestoneLevel('level2', '等级2', 2),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return [];
    }
}

// 测试用户类
class TestMember implements SessionHolder
{
    public function __construct(public int $id = 1)
    {
    }
    
    public function getOperatorTypeName(): string
    {
        return 'test_member';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('test_member');
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return null;
    }
}

beforeEach(function () {
    $this->configure = LunaMembershipConfigure::create()->build();
    $this->lunaHandler = app(LunaHandler::class);
    $this->membership = new LunaMembership(
        $this->configure,
        Cache::store('array'),
        $this->lunaHandler
    );
    
    // 创建测试处理器
    $handler = Handler::query()->forceCreate([
        'id' => 1000,
        'name' => SimpleMilestoneHandler::class,
        'group_id' => 1,
        'display_name' => '简单里程碑处理器',
        'description' => '测试用',
        'handler' => SimpleMilestoneHandler::class,
        'config' => [],
    ]);
});

test('创建里程碑类型', function () {
    $type = $this->membership->milestone()->createType(
        'vip_level',
        SimpleMilestoneHandler::class,
        [
            'display_name' => 'VIP等级',
            'description' => 'VIP会员等级体系',
            'config' => ['allow_downgrade' => false],
        ]
    );
    
    expect($type)->toBeInstanceOf(MembershipMilestoneType::class);
    expect($type->name)->toBe('vip_level');
    expect($type->display_name)->toBe('VIP等级');
    expect($type->handler_id)->toBe(hash_code(SimpleMilestoneHandler::class));
    expect($type->config['allow_downgrade'])->toBeFalse();
});

test('获取里程碑类型 - 使用缓存', function () {
    $this->membership->milestone()->createType('cached_type', SimpleMilestoneHandler::class);
    
    // 第一次获取
    $type1 = $this->membership->milestone()->getType('cached_type');
    expect($type1)->not->toBeNull();
    
    // 删除数据库记录
    MembershipMilestoneType::query()->where('name', 'cached_type')->delete();
    
    // 第二次获取应该从缓存中获取
    $type2 = $this->membership->milestone()->getType('cached_type');
    expect($type2)->not->toBeNull();
    expect($type2->name)->toBe('cached_type');
});

test('获取里程碑处理器', function () {
    $this->membership->milestone()->createType('test_type', SimpleMilestoneHandler::class);
    
    $handler = $this->membership->milestone()->getHandler('test_type');
    
    expect($handler)->toBeInstanceOf(SimpleMilestoneHandler::class);
    expect($handler->getMilestoneType())->not->toBeNull();
    expect($handler->getMilestoneType()->name)->toBe('test_type');
});

test('触发里程碑评估', function () {
    $this->membership->milestone()->createType('member_level', SimpleMilestoneHandler::class);
    $member = new TestMember(1);
    
    $level = $this->membership->milestone()->trigger($member, 'member_level');
    
    expect($level)->toBeInstanceOf(MilestoneLevel::class);
    expect($level->identifier)->toBe('level2'); // 默认返回最高等级
    
    // 验证保存了里程碑
    $milestone = MembershipMilestone::query()->first();
    expect($milestone)->not->toBeNull();
    expect($milestone->milestone)->toBe(hash_code('level2'));
});

test('批量触发多个里程碑类型', function () {
    $this->membership->milestone()->createType('type1', SimpleMilestoneHandler::class);
    $this->membership->milestone()->createType('type2', SimpleMilestoneHandler::class);
    
    $member = new TestMember(1);
    
    $results = $this->membership->milestone()->triggerMultiple($member, ['type1', 'type2', 'not_exist']);
    
    expect($results)->toHaveCount(3);
    expect($results['type1'])->toBeInstanceOf(MilestoneLevel::class);
    expect($results['type2'])->toBeInstanceOf(MilestoneLevel::class);
    expect($results['not_exist'])->toBeNull();
});

test('触发所有里程碑评估', function () {
    $this->membership->milestone()->createType('type1', SimpleMilestoneHandler::class);
    $this->membership->milestone()->createType('type2', SimpleMilestoneHandler::class);
    
    $member = new TestMember(1);
    
    $results = $this->membership->milestone()->triggerAll($member);
    
    expect($results)->toHaveCount(2);
    expect($results['type1'])->toBeInstanceOf(MilestoneLevel::class);
    expect($results['type2'])->toBeInstanceOf(MilestoneLevel::class);
});

test('获取会员当前里程碑 - 单个类型', function () {
    $this->membership->milestone()->createType('vip', SimpleMilestoneHandler::class);
    $member = new TestMember(1);
    
    // 先触发评估以创建里程碑
    $this->membership->milestone()->trigger($member, 'vip');
    
    $current = $this->membership->milestone()->getCurrent($member, 'vip');
    
    expect($current)->toBeInstanceOf(MilestoneLevel::class);
    expect($current->identifier)->toBe('level2');
});

test('获取会员所有里程碑', function () {
    $this->membership->milestone()->createType('vip', SimpleMilestoneHandler::class);
    $this->membership->milestone()->createType('points', SimpleMilestoneHandler::class);
    $member = new TestMember(1);
    
    // 触发评估
    $this->membership->milestone()->trigger($member, 'vip');
    $this->membership->milestone()->trigger($member, 'points');
    
    $milestones = $this->membership->milestone()->getCurrent($member);
    
    expect($milestones)->toBeArray();
    expect($milestones)->toHaveCount(2);
    expect($milestones['vip'])->toBeInstanceOf(MilestoneLevel::class);
    expect($milestones['points'])->toBeInstanceOf(MilestoneLevel::class);
});

test('获取里程碑历史', function () {
    $type = $this->membership->milestone()->createType('vip', SimpleMilestoneHandler::class);
    $member = new TestMember(1);
    
    // 创建一些里程碑变更
    $handler = $this->membership->milestone()->getHandler('vip');
    $levels = $handler->getMilestoneLevels();
    
    foreach ($levels as $level) {
        $handler->updateMilestone($member, $level);
        sleep(1); // 确保时间不同
    }
    
    $history = $this->membership->milestone()->getHistory($member, 'vip');
    
    expect($history)->toHaveCount(2);
    // 历史记录按创建时间降序排列，所以第一条应该是最新的 level2
    expect($history[0]->milestone)->toBe(hash_code('level2'));
    expect($history[1]->milestone)->toBe(hash_code('level1'));
});

test('检查是否达到过里程碑', function () {
    $this->membership->milestone()->createType('vip', SimpleMilestoneHandler::class);
    $member = new TestMember(1);
    
    // 触发评估创建里程碑
    $this->membership->milestone()->trigger($member, 'vip');
    
    $hasReached = $this->membership->milestone()->hasReached($member, 'vip', 'level2');
    expect($hasReached)->toBeTrue();
    
    $hasNotReached = $this->membership->milestone()->hasReached($member, 'vip', 'level3');
    expect($hasNotReached)->toBeFalse();
});

test('获取里程碑统计信息', function () {
    $this->membership->milestone()->createType('vip', SimpleMilestoneHandler::class);
    
    // 为多个会员创建里程碑
    $handler = $this->membership->milestone()->getHandler('vip');
    $levels = $handler->getMilestoneLevels();
    
    // 3个会员达到level1
    for ($i = 1; $i <= 3; $i++) {
        $member = new TestMember($i);
        $handler->updateMilestone($member, $levels[0]);
    }
    
    // 2个会员达到level2
    for ($i = 4; $i <= 5; $i++) {
        $member = new TestMember($i);
        $handler->updateMilestone($member, $levels[1]);
    }
    
    $stats = $this->membership->milestone()->getStatistics('vip');
    
    expect($stats)->toHaveCount(2);
    expect($stats['level1']['count'])->toBe(3);
    expect($stats['level2']['count'])->toBe(2);
    expect($stats['level1']['level'])->toBeInstanceOf(MilestoneLevel::class);
});

test('辅助函数 luna_membership', function () {
    app()->singleton(LunaMembership::class, function () use (&$membership) {
        return $this->membership;
    });
    
    $instance = luna_membership();
    
    expect($instance)->toBeInstanceOf(LunaMembership::class);
    expect($instance)->toBe($this->membership);
});