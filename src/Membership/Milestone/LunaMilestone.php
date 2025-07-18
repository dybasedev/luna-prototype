<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\MembershipBinding;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneLog;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 里程碑管理类
 *
 * 负责管理会员里程碑相关的所有功能
 */
class LunaMilestone
{
    /**
     * 缓存键前缀
     */
    const string CACHE_PREFIX = 'luna_membership:milestone:';

    /**
     * 构造函数
     *
     * @param LunaMembershipConfigure $configure 会员系统配置
     * @param Cache $cache 缓存接口
     * @param LunaHandler $handler 处理器管理器
     */
    public function __construct(
        protected LunaMembershipConfigure $configure,
        protected Cache $cache,
        protected LunaHandler $handler
    ) {
    }

    /**
     * 创建或更新里程碑类型
     *
     * @param string $name 类型名称
     * @param string $handlerClass 处理器类名
     * @param array $attributes 其他属性
     * @param bool $autoCreateInitialMilestones 是否自动为用户创建初始里程碑
     * @return MembershipMilestoneType
     */
    public function createType(string $name, string $handlerClass, array $attributes = [], bool $autoCreateInitialMilestones = true): MembershipMilestoneType
    {
        // 获取处理器实例以验证
        $handlerInstance = new $handlerClass();
        if (!$handlerInstance instanceof MemberMilestoneHandler) {
            throw new \InvalidArgumentException("Handler must be instance of MemberMilestoneHandler");
        }

        // 获取或创建处理器记录
        $handler = Handler::query()->firstOrCreate(
            ['name' => $handlerClass],
            [
                'group_id' => 0, // 默认分组
                'display_name' => $handlerInstance->handlerName(),
                'description' => $handlerInstance->handlerDescription(),
                'handler' => $handlerClass,
                'config' => [],
            ]
        );

        $milestoneTypeModel = $this->configure->milestoneTypeModel;
        
        // 检查是否是新创建的类型
        $isNewType = !$milestoneTypeModel::query()->where('name', $name)->exists();
        
        $milestoneType = $milestoneTypeModel::findOrCreateByName($name, array_merge($attributes, [
            'handler_id' => $handler->id,
            'display_name' => $attributes['display_name'] ?? $handlerInstance->handlerName(),
            'description' => $attributes['description'] ?? $handlerInstance->handlerDescription(),
            'config' => $attributes['config'] ?? [],
        ]));

        // 清除缓存
        $this->clearTypeCache($name);
        
        // 如果是新创建的类型且启用了自动创建初始里程碑
        if ($isNewType && $autoCreateInitialMilestones) {
            $this->createInitialMilestonesForAllBindings($milestoneType, $handlerInstance);
        }

        return $milestoneType;
    }

    /**
     * 获取里程碑类型
     *
     * @param string $name 类型名称
     * @return MembershipMilestoneType|null
     */
    public function getType(string $name): ?MembershipMilestoneType
    {
        $cacheKey = self::CACHE_PREFIX . 'type:' . $name;
        
        return $this->cache->remember($cacheKey, 3600, function () use ($name) {
            return $this->configure->milestoneTypeModel::query()->where('name', $name)->first();
        });
    }

    /**
     * 获取里程碑处理器
     *
     * @param string $typeName 里程碑类型名称
     * @return MemberMilestoneHandler|null
     */
    public function getHandler(string $typeName): ?MemberMilestoneHandler
    {
        $milestoneType = $this->getType($typeName);
        if (!$milestoneType) {
            return null;
        }

        // 获取处理器记录
        $handlerModel = Handler::query()->find($milestoneType->handler_id);
        if (!$handlerModel) {
            return null;
        }

        // 创建处理器实例
        $handlerClass = $handlerModel->handler ?: $handlerModel->name;
        if (!class_exists($handlerClass)) {
            return null;
        }

        $handler = new $handlerClass();
        if (!$handler instanceof MemberMilestoneHandler) {
            return null;
        }

        // 设置里程碑类型和配置
        $handler->setMilestoneType($milestoneType);
        if (!empty($milestoneType->config)) {
            $handler->withConfig($milestoneType->config);
        }

        return $handler;
    }

    /**
     * 触发里程碑评估
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param string $typeName 里程碑类型名称
     * @param array $context 触发上下文
     * @return MilestoneLevel|null 返回评估后的里程碑等级
     */
    public function trigger(SessionHolder $owner, string $typeName, array $context = []): ?MilestoneLevel
    {
        $handler = $this->getHandler($typeName);
        if (!$handler) {
            return null;
        }

        return $handler->trigger($owner, $context);
    }

    /**
     * 批量触发多个里程碑类型的评估
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param array<string> $typeNames 里程碑类型名称数组
     * @param array $context 触发上下文
     * @return array<string, MilestoneLevel|null> 返回各类型评估结果
     */
    public function triggerMultiple(SessionHolder $owner, array $typeNames, array $context = []): array
    {
        $results = [];
        
        foreach ($typeNames as $typeName) {
            $results[$typeName] = $this->trigger($owner, $typeName, $context);
        }
        
        return $results;
    }

    /**
     * 触发所有相关的里程碑评估
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param array $context 触发上下文
     * @return array<string, MilestoneLevel|null> 返回所有类型评估结果
     */
    public function triggerAll(SessionHolder $owner, array $context = []): array
    {
        // 获取所有启用的里程碑类型
        $milestoneTypes = $this->configure->milestoneTypeModel::query()->get();
        $results = [];
        
        foreach ($milestoneTypes as $type) {
            $results[$type->name] = $this->trigger($owner, $type->name, $context);
        }
        
        return $results;
    }

    /**
     * 获取会员的当前里程碑
     *
     * @param SessionHolder $owner 会员
     * @param string|null $typeName 里程碑类型名称，null则获取所有
     * @return MilestoneLevel|array<string, MilestoneLevel>|null
     */
    public function getCurrent(SessionHolder $owner, ?string $typeName = null): MilestoneLevel|array|null
    {
        if ($typeName) {
            $handler = $this->getHandler($typeName);
            return $handler ? $handler->getCurrentMilestone($owner) : null;
        }

        // 获取所有里程碑
        $milestones = $this->configure->milestoneModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', $owner->getOperatorType())
            ->with('milestoneType')
            ->get();

        $results = [];
        foreach ($milestones as $milestone) {
            $handler = $this->getHandler($milestone->milestoneType->name);
            if ($handler) {
                $level = $handler->getCurrentMilestone($owner);
                if ($level) {
                    $results[$milestone->milestoneType->name] = $level;
                }
            }
        }

        return $results;
    }

    /**
     * 获取里程碑历史记录
     *
     * @param SessionHolder $owner 会员
     * @param string $typeName 里程碑类型名称
     * @param int $limit 记录数量限制
     * @return Collection<MembershipMilestoneLog>
     */
    public function getHistory(SessionHolder $owner, string $typeName, int $limit = 10): Collection
    {
        $handler = $this->getHandler($typeName);
        if (!$handler) {
            return new Collection();
        }

        return $handler->getMilestoneHistory($owner, $limit);
    }

    /**
     * 检查是否曾经达到过某个里程碑
     *
     * @param SessionHolder $owner 会员
     * @param string $typeName 里程碑类型名称
     * @param string $milestoneIdentifier 里程碑标识符
     * @param \DateTimeInterface|null $since 从某个时间开始
     * @return bool
     */
    public function hasReached(SessionHolder $owner, string $typeName, string $milestoneIdentifier, ?\DateTimeInterface $since = null): bool
    {
        $handler = $this->getHandler($typeName);
        if (!$handler) {
            return false;
        }

        return $handler->hasReachedMilestone($owner, $milestoneIdentifier, $since);
    }

    /**
     * 获取里程碑统计信息
     *
     * @param string $typeName 里程碑类型名称
     * @return array<string, array{level: MilestoneLevel, count: int}>
     */
    public function getStatistics(string $typeName): array
    {
        $milestoneType = $this->getType($typeName);
        if (!$milestoneType) {
            return [];
        }

        $stats = $this->configure->milestoneModel::query()
            ->where('milestone_type_id', $milestoneType->id)
            ->select('milestone', DB::raw('count(*) as count'))
            ->groupBy('milestone')
            ->get()
            ->pluck('count', 'milestone')
            ->toArray();

        // 获取处理器以转换milestone标识符
        $handler = $this->getHandler($typeName);
        if (!$handler) {
            return $stats;
        }

        $levels = $handler->getFinalMilestoneLevels();
        $result = [];
        
        foreach ($levels as $level) {
            $hash = hash_code($level->identifier);
            $result[$level->identifier] = [
                'level' => $level,
                'count' => $stats[$hash] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * 清除里程碑类型缓存
     *
     * @param string $name 类型名称
     * @return void
     */
    protected function clearTypeCache(string $name): void
    {
        $cacheKey = self::CACHE_PREFIX . 'type:' . $name;
        $this->cache->forget($cacheKey);
    }
    
    /**
     * 为所有绑定的用户创建初始里程碑
     * 
     * @param MembershipMilestoneType $milestoneType 里程碑类型
     * @param MemberMilestoneHandler $handler 处理器实例
     * @return void
     */
    protected function createInitialMilestonesForAllBindings(MembershipMilestoneType $milestoneType, MemberMilestoneHandler $handler): void
    {
        // 设置里程碑类型到处理器
        $handler->setMilestoneType($milestoneType);
        if (!empty($milestoneType->config)) {
            $handler->withConfig($milestoneType->config);
        }
        
        // 获取所有里程碑等级
        $levels = $handler->getFinalMilestoneLevels();
        if (empty($levels)) {
            return;
        }
        
        // 找出无条件或条件最简单的初始等级（通常是序号最小的）
        $initialLevel = collect($levels)->sortBy('sequence')->first();
        if (!$initialLevel) {
            return;
        }
        
        // 检查初始等级是否有条件
        $conditions = $handler->getMilestoneConditions($initialLevel->identifier);
        
        // 遍历所有绑定配置
        foreach ($this->configure->bindings as $binding) {
            $this->createInitialMilestonesForBinding($binding, $milestoneType, $handler, $initialLevel, $conditions);
        }
    }
    
    /**
     * 为特定绑定创建初始里程碑
     * 
     * @param MembershipBinding $binding 绑定配置
     * @param MembershipMilestoneType $milestoneType 里程碑类型
     * @param MemberMilestoneHandler $handler 处理器实例
     * @param MilestoneLevel $initialLevel 初始等级
     * @param array $conditions 条件数组
     * @return void
     */
    protected function createInitialMilestonesForBinding(
        MembershipBinding $binding,
        MembershipMilestoneType $milestoneType,
        MemberMilestoneHandler $handler,
        MilestoneLevel $initialLevel,
        array $conditions
    ): void {
        // 构建查询
        if ($binding->table) {
            if ($binding->tableIsModelClass) {
                $query = $binding->table::query();
            } else {
                $query = DB::table($binding->table);
            }
        } else {
            $query = $binding->owner::query();
        }
        
        // 获取所有者类型
        $ownerType = (new $binding->owner)->getOperatorType();
        
        // 批量处理，每次100条
        $query->orderBy('id')->chunk(100, function ($owners) use ($milestoneType, $handler, $initialLevel, $conditions, $ownerType) {
            foreach ($owners as $owner) {
                // 如果是 Eloquent 模型，直接使用；否则创建临时 SessionHolder
                if ($owner instanceof SessionHolder) {
                    $sessionHolder = $owner;
                } else {
                    // 为非 SessionHolder 创建临时包装器
                    $sessionHolder = new class($owner, $ownerType) implements SessionHolder {
                        public function __construct(
                            private $owner,
                            private int $operatorType
                        ) {}
                        
                        public function getOperatorTypeName(): string
                        {
                            return 'membership_binding';
                        }
                        
                        public function getOperatorType(): int
                        {
                            return $this->operatorType;
                        }
                        
                        public function getOperatorId(): int
                        {
                            return $this->owner->id ?? 0;
                        }
                        
                        public function getSessionHolderContext(): ?array
                        {
                            // 返回数据库中的数据作为上下文
                            return (array) $this->owner;
                        }
                    };
                }
                
                // 检查是否已存在里程碑
                $existingMilestone = $this->configure->milestoneModel::query()
                    ->where('owner_id', $sessionHolder->getOperatorId())
                    ->where('owner_type', $sessionHolder->getOperatorType())
                    ->where('milestone_type_id', $milestoneType->id)
                    ->exists();
                    
                if (!$existingMilestone) {
                    // 如果没有条件，直接创建初始里程碑
                    if (empty($conditions)) {
                        // 直接更新为初始等级
                        $handler->updateMilestone($sessionHolder, $initialLevel);
                    } else {
                        // 有条件的话，使用 trigger 方法让处理器自己评估
                        // 需要传递上下文数据
                        $context = $sessionHolder->getSessionHolderContext() ?? [];
                        $handler->trigger($sessionHolder, $context);
                    }
                }
            }
        });
    }
}