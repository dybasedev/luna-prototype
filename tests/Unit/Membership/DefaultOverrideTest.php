<?php

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\MemberMilestoneHandler;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 默认启用配置覆盖的处理器
class DefaultOverrideHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '默认覆盖处理器';
    }

    public function handlerDescription(): string
    {
        return '默认启用配置覆盖的处理器';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('basic', 'Basic Level', 1, ['color' => 'gray']),
            new MilestoneLevel('advanced', 'Advanced Level', 2, ['color' => 'blue']),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return [];
    }
}

// 禁用配置覆盖的处理器
class NoOverrideHandler extends MemberMilestoneHandler
{
    public function handlerName(): string
    {
        return '禁用覆盖处理器';
    }

    public function handlerDescription(): string
    {
        return '禁用配置覆盖的处理器';
    }

    public function getMilestoneLevels(): array
    {
        return [
            new MilestoneLevel('basic', 'Basic Level', 1, ['color' => 'gray']),
            new MilestoneLevel('advanced', 'Advanced Level', 2, ['color' => 'blue']),
        ];
    }

    public function getMilestoneConditions(string $milestoneIdentifier): array
    {
        return [];
    }

    protected function enableConfigOverrides(): bool
    {
        return false;
    }
}

// 测试用户
class DefaultOverrideTestUser implements SessionHolder
{
    public function __construct(public int $id = 1) {}
    
    public function getOperatorTypeName(): string
    {
        return 'default_override_test';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('default_override_test');
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

it('默认启用配置覆盖', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 4000,
        'name' => DefaultOverrideHandler::class,
        'group_id' => 1,
        'display_name' => '默认覆盖处理器',
        'description' => '测试用',
        'handler' => DefaultOverrideHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型，带有等级覆盖配置
    $milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'default_override',
        'display_name' => '默认覆盖里程碑',
        'description' => '测试默认覆盖',
        'handler_id' => 4000,
        'config' => [
            'level_overrides' => [
                'basic' => [
                    'display_name' => '基础会员',
                    'metadata' => ['color' => 'green', 'bonus' => true]
                ]
            ]
        ],
    ]);
    
    $handler = new DefaultOverrideHandler();
    $handler->setMilestoneType($milestoneType);
    
    // 获取最终的里程碑等级
    $levels = $handler->getFinalMilestoneLevels();
    
    // 检查基础等级已被覆盖
    $basic = collect($levels)->firstWhere('identifier', 'basic');
    expect($basic->displayName)->toBe('基础会员'); // 覆盖的名称
    expect($basic->getMeta('color'))->toBe('green'); // 覆盖的颜色
    expect($basic->getMeta('bonus'))->toBeTrue(); // 新增的元数据
    
    // 高级等级保持不变
    $advanced = collect($levels)->firstWhere('identifier', 'advanced');
    expect($advanced->displayName)->toBe('Advanced Level');
    expect($advanced->getMeta('color'))->toBe('blue');
});

it('在 handler 中禁用配置覆盖', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 4001,
        'name' => NoOverrideHandler::class,
        'group_id' => 1,
        'display_name' => '禁用覆盖处理器',
        'description' => '测试用',
        'handler' => NoOverrideHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型，带有等级覆盖配置
    $milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'no_override',
        'display_name' => '禁用覆盖里程碑',
        'description' => '测试禁用覆盖',
        'handler_id' => 4001,
        'config' => [
            'level_overrides' => [
                'basic' => [
                    'display_name' => '基础会员',
                    'metadata' => ['color' => 'green']
                ]
            ]
        ],
    ]);
    
    $handler = new NoOverrideHandler();
    $handler->setMilestoneType($milestoneType);
    
    // 获取最终的里程碑等级
    $levels = $handler->getFinalMilestoneLevels();
    
    // 检查基础等级未被覆盖
    $basic = collect($levels)->firstWhere('identifier', 'basic');
    expect($basic->displayName)->toBe('Basic Level'); // 原始名称
    expect($basic->getMeta('color'))->toBe('gray'); // 原始颜色
});

it('evaluate 方法使用最终的里程碑等级', function () {
    // 创建处理器记录
    Handler::query()->forceCreate([
        'id' => 4002,
        'name' => DefaultOverrideHandler::class,
        'group_id' => 1,
        'display_name' => '默认覆盖处理器',
        'description' => '测试用',
        'handler' => DefaultOverrideHandler::class,
        'config' => [],
    ]);
    
    // 创建里程碑类型，修改顺序
    $milestoneType = MembershipMilestoneType::query()->create([
        'name' => 'evaluate_override',
        'display_name' => '评估覆盖里程碑',
        'description' => '测试评估覆盖',
        'handler_id' => 4002,
        'config' => [
            'level_overrides' => [
                'basic' => [
                    'sequence' => 10, // 把 basic 的顺序改为更高
                ],
                'advanced' => [
                    'sequence' => 1, // 把 advanced 的顺序改为更低
                ]
            ]
        ],
    ]);
    
    $handler = new DefaultOverrideHandler();
    $handler->setMilestoneType($milestoneType);
    
    $user = new DefaultOverrideTestUser();
    
    // 评估时应该使用覆盖后的顺序
    $levels = $handler->getFinalMilestoneLevels();
    $sortedLevels = collect($levels)->sortByDesc('sequence')->values();
    
    // 验证顺序已被覆盖
    expect($sortedLevels[0]->identifier)->toBe('basic'); // basic 现在顺序更高
    expect($sortedLevels[1]->identifier)->toBe('advanced'); // advanced 现在顺序更低
});