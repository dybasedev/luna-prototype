<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\Conditions;

use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;
use Dybasedev\LunaPrototype\Membership\LunaMembership;

/**
 * 历史里程碑条件
 * 
 * 用于判断是否曾经达到过某个里程碑
 */
class HistoryCondition implements MilestoneCondition
{
    /**
     * @param LunaMembership $membership 会员系统实例
     * @param string $milestoneTypeName 里程碑类型名称
     * @param string $milestoneIdentifier 里程碑标识符
     * @param \DateTimeInterface|null $since 从某个时间开始
     * @param string|null $identifier 条件标识符
     * @param string|null $description 条件描述
     */
    public function __construct(
        protected LunaMembership $membership,
        protected string $milestoneTypeName,
        protected string $milestoneIdentifier,
        protected ?\DateTimeInterface $since = null,
        protected ?string $identifier = null,
        protected ?string $description = null
    ) {
    }

    /**
     * 判断是否满足条件
     *
     * @param mixed $owner 里程碑所有者
     * @param array $context 判断上下文
     * @return bool
     */
    public function isSatisfied(mixed $owner, array $context = []): bool
    {
        return $this->membership->hasReachedMilestone(
            $owner,
            $this->milestoneTypeName,
            $this->milestoneIdentifier,
            $this->since
        );
    }

    /**
     * 获取条件的唯一标识
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        if ($this->identifier) {
            return $this->identifier;
        }
        
        $since = $this->since ? $this->since->format('Y-m-d') : 'anytime';
        return "history_{$this->milestoneTypeName}_{$this->milestoneIdentifier}_{$since}";
    }

    /**
     * 获取条件描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }
        
        $desc = "曾经达到过 {$this->milestoneTypeName} 的 {$this->milestoneIdentifier} 里程碑";
        if ($this->since) {
            $desc .= "（从 {$this->since->format('Y-m-d')} 开始）";
        }
        
        return $desc;
    }

    /**
     * 获取条件配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'milestone_type_name' => $this->milestoneTypeName,
            'milestone_identifier' => $this->milestoneIdentifier,
            'since' => $this->since?->format('Y-m-d H:i:s'),
        ];
    }
}