<?php

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试用的里程碑处理器
class OverridableMilestoneHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '可覆盖里程碑处理器';
    }

    public function handlerDescription(): string
    {
        return '支持配置覆盖的里程碑处理器';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('bronze', 'Bronze Member', 1, ['icon' => 'bronze.png']),
            new MilestoneLevel('silver', 'Silver Member', 2, ['icon' => 'silver.png']),
            new MilestoneLevel('gold', 'Gold Member', 3, ['icon' => 'gold.png']),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return [];
    }
}

// 测试用户
class OverrideTestUser implements SessionHolder
{
    public function __construct(public int $id = 1) {}
    
    public function getOperatorTypeName(): string
    {
        return 'override_test_user';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('override_test_user');
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return [];
    }
}

test('MilestoneLevel 支持配置覆盖', function () {
    $level = new MilestoneLevel('bronze', 'Bronze Member', 1, ['icon' => 'bronze.png']);
    
    $overridden = $level->withOverrides([
        'display_name' => '青铜会员',
        'sequence' => 10,
        'metadata' => ['icon' => 'bronze_new.png', 'badge' => 'bronze_badge.png']
    ]);
    
    expect($overridden->identifier)->toBe('bronze'); // 标识符不变
    expect($overridden->displayName)->toBe('青铜会员');
    expect($overridden->sequence)->toBe(10);
    expect($overridden->getMeta('icon'))->toBe('bronze_new.png');
    expect($overridden->getMeta('badge'))->toBe('bronze_badge.png');
});

test('MilestoneLevel::fromConfig 支持字符串配置', function () {
    $level = MilestoneLevel::fromConfig('bronze', 5);
    
    expect($level->identifier)->toBe('bronze');
    expect($level->displayName)->toBe('bronze');
    expect($level->sequence)->toBe(5);
    expect($level->metadata)->toBe([]);
});

test('MilestoneLevel::fromConfig 支持数组配置', function () {
    $level = MilestoneLevel::fromConfig([
        'identifier' => 'bronze',
        'display_name' => '青铜会员',
        'sequence' => 1,
        'metadata' => ['icon' => 'bronze.png']
    ]);
    
    expect($level->identifier)->toBe('bronze');
    expect($level->displayName)->toBe('青铜会员');
    expect($level->sequence)->toBe(1);
    expect($level->getMeta('icon'))->toBe('bronze.png');
});

test('Handler 获取带配置覆盖的里程碑等级', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 3000,
        'name' => OverridableMilestoneHandler::class,
        'group_id' => 1,
        'display_name' => '可覆盖里程碑处理器',
        'description' => '测试用',
        'handler' => OverridableMilestoneHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型，带有等级覆盖配置
    $milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'override_milestone',
        'display_name' => '可覆盖里程碑',
        'description' => '支持配置覆盖',
        'handler_id' => 3000,
        'config' => [
            'level_overrides' => [
                'bronze' => [
                    'display_name' => '青铜会员',
                    'metadata' => ['icon' => 'bronze_custom.png', 'description' => '初级会员']
                ],
                'silver' => [
                    'display_name' => '白银会员',
                    'sequence' => 5
                ]
            ]
        ],
    ]);
    
    $handler = new OverridableMilestoneHandler();
    $handler->setMilestoneType($milestoneType);
    
    $levels = $handler->getMilestoneLevelsWithOverrides();
    
    // 检查青铜等级的覆盖
    $bronze = collect($levels)->firstWhere('identifier', 'bronze');
    expect($bronze->displayName)->toBe('青铜会员');
    expect($bronze->sequence)->toBe(1); // 序号未覆盖
    expect($bronze->getMeta('icon'))->toBe('bronze_custom.png');
    expect($bronze->getMeta('description'))->toBe('初级会员');
    
    // 检查白银等级的覆盖
    $silver = collect($levels)->firstWhere('identifier', 'silver');
    expect($silver->displayName)->toBe('白银会员');
    expect($silver->sequence)->toBe(5);
    expect($silver->getMeta('icon'))->toBe('silver.png'); // 元数据未覆盖
    
    // 检查黄金等级未覆盖
    $gold = collect($levels)->firstWhere('identifier', 'gold');
    expect($gold->displayName)->toBe('Gold Member');
    expect($gold->sequence)->toBe(3);
});

test('覆盖配置在获取当前里程碑时生效', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 3001,
        'name' => OverridableMilestoneHandler::class,
        'group_id' => 1,
        'display_name' => '可覆盖里程碑处理器',
        'description' => '测试用',
        'handler' => OverridableMilestoneHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型，带有等级覆盖配置
    $milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'override_milestone2',
        'display_name' => '可覆盖里程碑2',
        'description' => '支持配置覆盖',
        'handler_id' => 3001,
        'config' => [
            'level_overrides' => [
                'silver' => [
                    'display_name' => '白银VIP',
                    'metadata' => ['vip' => true]
                ]
            ]
        ],
    ]);
    
    $handler = new OverridableMilestoneHandler();
    $handler->setMilestoneType($milestoneType);
    
    $user = new OverrideTestUser(1);
    
    // 创建一个里程碑记录
    MembershipMilestone::query()->create([
        'owner_id' => 1,
        'owner_type' => hash_code('override_test_user'),
        'milestone_type_id' => $milestoneType->id,
        'milestone' => hash_code('silver'),
        'payload' => [],
    ]);
    
    // 获取当前里程碑
    $current = $handler->getCurrentMilestone($user);
    
    expect($current)->not->toBeNull();
    expect($current->identifier)->toBe('silver');
    expect($current->displayName)->toBe('白银VIP'); // 使用了覆盖的显示名称
    expect($current->getMeta('vip'))->toBeTrue();
});