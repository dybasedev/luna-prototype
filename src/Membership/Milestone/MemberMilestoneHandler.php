<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\ModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelInstance;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneLog;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Illuminate\Support\Collection;

/**
 * 会员里程碑处理器基类
 * 
 * 提供里程碑体系的核心功能，包括等级定义、条件判断、升降级逻辑等
 * 子类需要实现具体的里程碑等级定义和条件判断逻辑
 */
abstract class MemberMilestoneHandler extends BaseHandler implements ModelHandler
{
    use WithModelInstance;

    /**
     * 里程碑条件集合
     *
     * @var Collection<MilestoneCondition>
     */
    protected Collection $conditions;

    /**
     * 里程碑类型模型
     *
     * @var MembershipMilestoneType|null
     */
    protected ?MembershipMilestoneType $milestoneType = null;

    /**
     * 会员模块配置
     *
     * @var LunaMembershipConfigure|null
     */
    protected ?LunaMembershipConfigure $membershipConfig = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->conditions = new Collection();
    }

    /**
     * 获取关联的配置仓库类
     *
     * @return class-string<MilestoneConfiguration>
     */
    public static function configurationRepository(): string
    {
        return MilestoneConfiguration::class;
    }

    /**
     * 获取配置
     *
     * @return MilestoneConfiguration
     */
    public function getConfig(): MilestoneConfiguration
    {
        if (!$this->config instanceof MilestoneConfiguration) {
            $this->config = new MilestoneConfiguration([]);
        }
        
        return $this->config;
    }

    /**
     * 获取会员模块配置
     *
     * @return LunaMembershipConfigure
     */
    protected function getMembershipConfig(): LunaMembershipConfigure
    {
        if (!$this->membershipConfig) {
            $this->membershipConfig = luna_module_configure(LunaMembershipConfigure::class);
        }
        
        return $this->membershipConfig;
    }

    /**
     * 获取里程碑等级定义
     * 
     * 子类需要实现此方法，返回所有可用的里程碑等级
     *
     * @return array<MilestoneLevel>
     */
    abstract public function getMilestoneLevels(): array;

    /**
     * 获取里程碑达成条件
     * 
     * 子类需要实现此方法，返回特定里程碑的达成条件
     *
     * @param string $milestoneIdentifier 里程碑标识符
     * @return array<MilestoneCondition>
     */
    abstract public function getMilestoneConditions(string $milestoneIdentifier): array;

    /**
     * 是否启用配置覆盖
     * 
     * 默认启用，子类可以覆盖此方法来禁用配置覆盖
     *
     * @return bool
     */
    protected function enableConfigOverrides(): bool
    {
        return true;
    }

    /**
     * 设置里程碑类型
     *
     * @param MembershipMilestoneType $milestoneType
     * @return $this
     */
    public function setMilestoneType(MembershipMilestoneType $milestoneType): static
    {
        $this->milestoneType = $milestoneType;
        return $this;
    }

    /**
     * 获取里程碑类型
     *
     * @return MembershipMilestoneType|null
     */
    public function getMilestoneType(): ?MembershipMilestoneType
    {
        return $this->milestoneType;
    }

    /**
     * 获取带配置覆盖的里程碑等级
     * 
     * 如果启用了配置覆盖且里程碑类型模型中有配置覆盖，则应用这些覆盖
     *
     * @return array<MilestoneLevel>
     */
    public function getMilestoneLevelsWithOverrides(): array
    {
        $levels = $this->getMilestoneLevels();
        
        // 如果未启用配置覆盖，直接返回原始等级
        if (!$this->enableConfigOverrides()) {
            return $levels;
        }
        
        // 如果没有里程碑类型或没有配置，直接返回原始等级
        if (!$this->milestoneType || empty($this->milestoneType->config['level_overrides'])) {
            return $levels;
        }
        
        $overrides = $this->milestoneType->config['level_overrides'];
        $result = [];
        
        foreach ($levels as $level) {
            if (isset($overrides[$level->identifier])) {
                $result[] = $level->withOverrides($overrides[$level->identifier]);
            } else {
                $result[] = $level;
            }
        }
        
        return $result;
    }

    /**
     * 获取最终的里程碑等级列表
     * 
     * 这是获取里程碑等级的主要方法，会自动应用配置覆盖（如果启用）
     *
     * @return array<MilestoneLevel>
     */
    public function getFinalMilestoneLevels(): array
    {
        return $this->getMilestoneLevelsWithOverrides();
    }

    /**
     * 评估里程碑
     * 
     * 根据当前条件评估应该达到的里程碑等级
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param array $context 评估上下文
     * @return MilestoneLevel|null 返回应该达到的里程碑等级，null 表示不满足任何里程碑
     */
    public function evaluate(SessionHolder $owner, array $context = []): ?MilestoneLevel
    {
        $levels = $this->getFinalMilestoneLevels();
        $config = $this->getConfig();
        
        // 如果不允许降级，需要获取当前里程碑
        $currentMilestone = null;
        if (!$config->allowDowngrade()) {
            $currentMilestone = $this->getCurrentMilestone($owner);
        }
        
        // 从高到低评估里程碑
        $sortedLevels = collect($levels)->sortByDesc('sequence');
        
        foreach ($sortedLevels as $level) {
            // 如果不允许降级，跳过低于当前等级的里程碑
            if ($currentMilestone && !$config->allowDowngrade()) {
                if ($level->sequence < $currentMilestone->sequence) {
                    continue;
                }
            }
            
            if ($this->checkConditions($level, $owner, $context)) {
                return $level;
            }
        }
        
        // 如果不允许降级且有当前里程碑，返回当前里程碑
        if ($currentMilestone && !$config->allowDowngrade()) {
            return $currentMilestone;
        }
        
        return null;
    }

    /**
     * 检查里程碑条件
     *
     * @param MilestoneLevel $level 里程碑等级
     * @param SessionHolder $owner 所有者
     * @param array $context 上下文
     * @return bool
     */
    protected function checkConditions(MilestoneLevel $level, SessionHolder $owner, array $context): bool
    {
        $conditions = $this->getMilestoneConditions($level->identifier);
        
        if (empty($conditions)) {
            return true;
        }
        
        $strategy = $this->getConfig()->getConditionStrategy();
        
        foreach ($conditions as $condition) {
            $satisfied = $condition->isSatisfied($owner, $context);
            
            if ($strategy === 'any' && $satisfied) {
                return true;
            }
            
            if ($strategy === 'all' && !$satisfied) {
                return false;
            }
        }
        
        return $strategy === 'all';
    }

    /**
     * 获取当前里程碑
     *
     * @param SessionHolder $owner
     * @return MilestoneLevel|null
     */
    public function getCurrentMilestone(SessionHolder $owner): ?MilestoneLevel
    {
        if (!$this->milestoneType) {
            return null;
        }
        
        $milestoneModel = $this->getMembershipConfig()->milestoneModel;
        $milestone = $milestoneModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', $owner->getOperatorType())
            ->where('milestone_type_id', $this->milestoneType->id)
            ->first();
            
        if (!$milestone) {
            return null;
        }
        
        // 从配置的等级中查找
        $levels = $this->getFinalMilestoneLevels();
        foreach ($levels as $level) {
            if (hash_code($level->identifier) == $milestone->milestone) {
                return $level;
            }
        }
        
        return null;
    }

    /**
     * 更新里程碑
     *
     * @param SessionHolder $owner 所有者
     * @param MilestoneLevel $level 新的里程碑等级
     * @param array $payload 额外数据
     * @return MembershipMilestone
     */
    public function updateMilestone(SessionHolder $owner, MilestoneLevel $level, array $payload = []): MembershipMilestone
    {
        if (!$this->milestoneType) {
            throw new \RuntimeException('Milestone type not set');
        }
        
        $currentMilestone = $this->getCurrentMilestone($owner);
        $beforeMilestone = $currentMilestone;
        
        // 如果配置了记录跳过的里程碑，先记录中间的里程碑
        if ($this->getConfig()->recordSkippedMilestones()) {
            $lastSkipped = $this->recordSkippedMilestones($owner, $currentMilestone, $level, $payload);
            if ($lastSkipped) {
                $beforeMilestone = $lastSkipped;
            }
        }
        
        $milestoneModel = $this->getMembershipConfig()->milestoneModel;
        $milestone = $milestoneModel::query()->updateOrCreate(
            [
                'owner_id' => $owner->getOperatorId(),
                'owner_type' => $owner->getOperatorType(),
                'milestone_type_id' => $this->milestoneType->id,
            ],
            [
                'milestone' => hash_code($level->identifier),
                'payload' => array_merge($level->metadata, $payload),
            ]
        );
        
        // 强制记录变更日志（recordHistory 现在总是返回 true）
        $logModel = $this->getMembershipConfig()->milestoneLogModel;
        $logModel::query()->create([
            'owner_id' => $owner->getOperatorId(),
            'owner_type' => $owner->getOperatorType(),
            'milestone_type_id' => $this->milestoneType->id,
            'milestone' => hash_code($level->identifier),
            'before_milestone' => $beforeMilestone ? hash_code($beforeMilestone->identifier) : null,
            'payload' => array_merge($level->metadata, $payload, [
                'changed_at' => now()->toIso8601String(),
            ]),
        ]);
        
        return $milestone;
    }

    /**
     * 获取里程碑历史记录
     *
     * @param SessionHolder $owner
     * @param int $limit
     * @return Collection<MembershipMilestoneLog>
     */
    public function getMilestoneHistory(SessionHolder $owner, int $limit = 10): Collection
    {
        if (!$this->milestoneType) {
            return collect();
        }
        
        $logModel = $this->getMembershipConfig()->milestoneLogModel;
        return $logModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', $owner->getOperatorType())
            ->where('milestone_type_id', $this->milestoneType->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 检查是否曾经达到过某个里程碑
     *
     * @param SessionHolder $owner
     * @param string $milestoneIdentifier
     * @param \DateTimeInterface|null $since 从某个时间开始
     * @return bool
     */
    public function hasReachedMilestone(SessionHolder $owner, string $milestoneIdentifier, ?\DateTimeInterface $since = null): bool
    {
        if (!$this->milestoneType) {
            return false;
        }
        
        $logModel = $this->getMembershipConfig()->milestoneLogModel;
        $query = $logModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', $owner->getOperatorType())
            ->where('milestone_type_id', $this->milestoneType->id)
            ->where('milestone', hash_code($milestoneIdentifier));
            
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        
        return $query->exists();
    }

    /**
     * 触发里程碑评估
     * 
     * 评估并更新里程碑（如果需要）
     *
     * @param SessionHolder $owner
     * @param array $context
     * @return MilestoneLevel|null 返回更新后的里程碑等级
     */
    public function trigger(SessionHolder $owner, array $context = []): ?MilestoneLevel
    {
        $newLevel = $this->evaluate($owner, $context);
        
        if (!$newLevel) {
            return null;
        }
        
        $currentMilestone = $this->getCurrentMilestone($owner);
        
        // 如果里程碑发生变化，更新它
        if (!$currentMilestone || $currentMilestone->identifier !== $newLevel->identifier) {
            $this->updateMilestone($owner, $newLevel, $context);
        }
        
        return $newLevel;
    }

    /**
     * 记录跳过的里程碑
     * 
     * 当用户直接达到更高等级时，记录中间跳过的所有满足条件的里程碑
     *
     * @param SessionHolder $owner 所有者
     * @param MilestoneLevel|null $currentLevel 当前等级
     * @param MilestoneLevel $targetLevel 目标等级
     * @param array $payload 额外数据
     * @return MilestoneLevel|null 返回最后一个跳过的里程碑
     */
    protected function recordSkippedMilestones(
        SessionHolder $owner, 
        ?MilestoneLevel $currentLevel, 
        MilestoneLevel $targetLevel,
        array $payload = []
    ): ?MilestoneLevel {
        $levels = $this->getFinalMilestoneLevels();
        $currentSequence = $currentLevel ? $currentLevel->sequence : 0;
        $targetSequence = $targetLevel->sequence;
        
        // 找出所有中间的里程碑
        $skippedLevels = [];
        foreach ($levels as $level) {
            if ($level->sequence > $currentSequence && $level->sequence < $targetSequence) {
                // 检查是否满足条件
                if ($this->checkConditions($level, $owner, $payload)) {
                    $skippedLevels[] = $level;
                }
            }
        }
        
        // 按顺序记录跳过的里程碑
        usort($skippedLevels, fn($a, $b) => $a->sequence <=> $b->sequence);
        
        foreach ($skippedLevels as $skippedLevel) {
            $logModel = $this->getMembershipConfig()->milestoneLogModel;
            $logModel::query()->create([
                'owner_id' => $owner->getOperatorId(),
                'owner_type' => $owner->getOperatorType(),
                'milestone_type_id' => $this->milestoneType->id,
                'milestone' => hash_code($skippedLevel->identifier),
                'before_milestone' => $currentLevel ? hash_code($currentLevel->identifier) : null,
                'payload' => array_merge($skippedLevel->metadata, $payload, [
                    'changed_at' => now()->toIso8601String(),
                    'skipped' => true, // 标记为跳过的里程碑
                ]),
            ]);
            
            // 更新当前等级，为下一个跳过的里程碑做准备
            $currentLevel = $skippedLevel;
        }
        
        return $currentLevel;
    }
}