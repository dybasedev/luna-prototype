<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\Conditions;

use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;

/**
 * 日期范围条件
 * 
 * 用于判断当前日期是否在指定范围内
 */
class DateRangeCondition implements MilestoneCondition
{
    /**
     * @param \DateTimeInterface|null $startDate 开始日期
     * @param \DateTimeInterface|null $endDate 结束日期
     * @param string|null $identifier 条件标识符
     * @param string|null $description 条件描述
     */
    public function __construct(
        protected ?\DateTimeInterface $startDate = null,
        protected ?\DateTimeInterface $endDate = null,
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
        $currentDate = $context['current_date'] ?? new \DateTime();
        
        if (!$currentDate instanceof \DateTimeInterface) {
            $currentDate = new \DateTime($currentDate);
        }
        
        if ($this->startDate && $currentDate < $this->startDate) {
            return false;
        }
        
        if ($this->endDate && $currentDate > $this->endDate) {
            return false;
        }
        
        return true;
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
        
        $start = $this->startDate ? $this->startDate->format('Y-m-d') : 'unlimited';
        $end = $this->endDate ? $this->endDate->format('Y-m-d') : 'unlimited';
        
        return "date_range_{$start}_{$end}";
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
        
        if ($this->startDate && $this->endDate) {
            return "日期在 {$this->startDate->format('Y-m-d')} 到 {$this->endDate->format('Y-m-d')} 之间";
        } elseif ($this->startDate) {
            return "日期在 {$this->startDate->format('Y-m-d')} 之后";
        } elseif ($this->endDate) {
            return "日期在 {$this->endDate->format('Y-m-d')} 之前";
        }
        
        return "任意日期";
    }

    /**
     * 获取条件配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'start_date' => $this->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate?->format('Y-m-d H:i:s'),
        ];
    }
}