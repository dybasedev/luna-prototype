<?php

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembership;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\MembershipBinding;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\NumericCondition;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// 测试用户模型
class AutoTestUser implements SessionHolder
{
    public function __construct(
        public int $id = 1,
        public int $points = 0
    ) {}
    
    public function getOperatorTypeName(): string
    {
        return 'auto_test_user';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('auto_test_user');
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

// 有初始等级的处理器
class InitialLevelHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '初始等级处理器';
    }

    public function handlerDescription(): string
    {
        return '有初始等级的里程碑系统';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('beginner', '新手', 1), // 无条件的初始等级
            new MilestoneLevel('intermediate', '中级', 2),
            new MilestoneLevel('advanced', '高级', 3),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'beginner' => [], // 无条件
            'intermediate' => [new NumericCondition('points', '>=', 100)],
            'advanced' => [new NumericCondition('points', '>=', 500)],
            default => [],
        };
    }
}

// 有条件初始等级的处理器
class ConditionalInitialHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '条件初始等级处理器';
    }

    public function handlerDescription(): string
    {
        return '初始等级也有条件的里程碑系统';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('bronze', '青铜', 1),
            new MilestoneLevel('silver', '白银', 2),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return match ($milestoneIdentifier) {
            'bronze' => [new NumericCondition('points', '>=', 50)],
            'silver' => [new NumericCondition('points', '>=', 200)],
            default => [],
        };
    }
}

beforeEach(function () {
    // 创建测试表
    DB::statement('CREATE TABLE IF NOT EXISTS auto_test_users (
        id INT PRIMARY KEY,
        points INT DEFAULT 0
    )');
    
    // 创建测试用户
    DB::table('auto_test_users')->insert([
        ['id' => 1, 'points' => 0],    // 不满足任何条件
        ['id' => 2, 'points' => 60],   // 满足青铜条件
        ['id' => 3, 'points' => 150],  // 满足中级条件
        ['id' => 4, 'points' => 600],  // 满足高级条件
    ]);
    
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 6000,
        'name' => InitialLevelHandler::class,
        'group_id' => 1,
        'display_name' => '初始等级处理器',
        'description' => '测试用',
        'handler' => InitialLevelHandler::class,
        'config' => [],
    ]);
    
    Handler::query()->forceCreate([
        'id' => 6001,
        'name' => ConditionalInitialHandler::class,
        'group_id' => 1,
        'display_name' => '条件初始等级处理器',
        'description' => '测试用',
        'handler' => ConditionalInitialHandler::class,
        'config' => [],
    ]);
});

afterEach(function () {
    // 清理测试表
    DB::statement('DROP TABLE IF EXISTS auto_test_users');
});

test('创建里程碑类型时自动为所有用户创建无条件的初始里程碑', function () {
    // 配置会员系统绑定
    $binding = new MembershipBinding(AutoTestUser::class);
    $binding->table('auto_test_users');
    
    $configure = LunaMembershipConfigure::create()
        ->bind($binding)
        ->build();
    
    $membership = new LunaMembership(
        $configure,
        app('cache.store'),
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
    );
    
    // 创建里程碑类型（自动创建初始里程碑）
    $milestone = $membership->milestone();
    $milestone->createType('auto_initial', InitialLevelHandler::class);
    
    // 验证所有用户都获得了初始里程碑
    $milestones = MembershipMilestone::query()->get();
    expect($milestones)->toHaveCount(4); // 4个用户都应该有初始里程碑
    
    // 验证所有用户都是 beginner 等级
    foreach ($milestones as $milestone) {
        expect($milestone->milestone)->toBe(hash_code('beginner'));
    }
});

test('创建里程碑类型时只为满足条件的用户创建初始里程碑', function () {
    // 配置会员系统绑定
    $binding = new MembershipBinding(AutoTestUser::class);
    $binding->table('auto_test_users');
    
    $configure = LunaMembershipConfigure::create()
        ->bind($binding)
        ->build();
    
    $membership = new LunaMembership(
        $configure,
        app('cache.store'),
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
    );
    
    // 创建里程碑类型（自动创建初始里程碑）
    $milestone = $membership->milestone();
    $milestone->createType('conditional_initial', ConditionalInitialHandler::class);
    
    // 验证只有满足条件的用户获得了初始里程碑
    $milestones = MembershipMilestone::query()->get();
    expect($milestones)->toHaveCount(3); // 只有3个用户满足青铜条件（points >= 50）
    
    // 验证用户的里程碑等级
    $userMilestones = MembershipMilestone::query()
        ->pluck('milestone', 'owner_id')
        ->toArray();
    
    expect($userMilestones)->not->toHaveKey(1); // 用户1不满足条件
    expect($userMilestones[2])->toBe(hash_code('bronze')); // 用户2是青铜
    expect($userMilestones[3])->toBe(hash_code('bronze')); // 用户3获得青铜（初始创建只给最低符合条件的等级）
    expect($userMilestones[4])->toBe(hash_code('silver')); // 用户4获得银牌（满足银牌条件）
});

test('禁用自动创建初始里程碑', function () {
    // 配置会员系统绑定
    $binding = new MembershipBinding(AutoTestUser::class);
    $binding->table('auto_test_users');
    
    $configure = LunaMembershipConfigure::create()
        ->bind($binding)
        ->build();
    
    $membership = new LunaMembership(
        $configure,
        app('cache.store'),
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
    );
    
    // 创建里程碑类型，但禁用自动创建
    $milestone = $membership->milestone();
    $milestone->createType('no_auto_create', InitialLevelHandler::class, [], false);
    
    // 验证没有创建任何里程碑
    $milestones = MembershipMilestone::query()->get();
    expect($milestones)->toHaveCount(0);
});

test('更新已存在的里程碑类型不会重复创建里程碑', function () {
    // 配置会员系统绑定
    $binding = new MembershipBinding(AutoTestUser::class);
    $binding->table('auto_test_users');
    
    $configure = LunaMembershipConfigure::create()
        ->bind($binding)
        ->build();
    
    $membership = new LunaMembership(
        $configure,
        app('cache.store'),
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
    );
    
    $milestone = $membership->milestone();
    
    // 第一次创建
    $milestone->createType('update_test', InitialLevelHandler::class);
    $firstCount = MembershipMilestone::query()->count();
    expect($firstCount)->toBe(4);
    
    // 第二次创建（实际是更新）
    $milestone->createType('update_test', InitialLevelHandler::class, [
        'display_name' => '更新后的名称'
    ]);
    $secondCount = MembershipMilestone::query()->count();
    expect($secondCount)->toBe(4); // 数量不变
});

test('使用SessionHolder模型创建初始里程碑', function () {
    // 创建实现 SessionHolder 的用户模型
    $user1 = new AutoTestUser(101, 0);
    $user2 = new AutoTestUser(102, 100);
    
    // 手动保存到数据库
    DB::table('auto_test_users')->insert([
        ['id' => 101, 'points' => 0],
        ['id' => 102, 'points' => 100],
    ]);
    
    // 配置会员系统绑定
    $binding = new MembershipBinding(AutoTestUser::class);
    $binding->table('auto_test_users');
    
    $configure = LunaMembershipConfigure::create()
        ->bind($binding)
        ->build();
    
    $membership = new LunaMembership(
        $configure,
        app('cache.store'),
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class)
    );
    
    // 创建里程碑类型
    $milestone = $membership->milestone();
    $milestone->createType('session_holder_test', InitialLevelHandler::class);
    
    // 验证创建了里程碑
    $milestones = MembershipMilestone::query()
        ->whereIn('owner_id', [101, 102])
        ->get();
    
    expect($milestones)->toHaveCount(2);
    
    // 都应该是初始等级
    foreach ($milestones as $milestone) {
        expect($milestone->milestone)->toBe(hash_code('beginner'));
    }
});