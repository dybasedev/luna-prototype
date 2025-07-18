<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * 里程碑配置仓库
 * 
 * 用于管理里程碑处理器的配置信息
 */
class MilestoneConfiguration extends Repository
{
    /**
     * 获取里程碑是否允许降级
     *
     * @return bool
     */
    public function allowDowngrade(): bool
    {
        return $this->get('allow_downgrade', false);
    }

    /**
     * 获取里程碑是否自动升级
     *
     * @return bool
     */
    public function autoUpgrade(): bool
    {
        return $this->get('auto_upgrade', true);
    }

    /**
     * 获取里程碑重新评估周期（秒）
     * 
     * @return int|null null 表示不自动重新评估
     */
    public function reevaluationPeriod(): ?int
    {
        return $this->get('reevaluation_period');
    }

    /**
     * 获取里程碑等级定义
     *
     * @return array<MilestoneLevel>
     */
    public function getLevels(): array
    {
        $levels = $this->get('levels', []);
        $result = [];
        
        foreach ($levels as $level) {
            if ($level instanceof MilestoneLevel) {
                $result[] = $level;
            } else {
                $result[] = new MilestoneLevel(
                    $level['identifier'] ?? '',
                    $level['display_name'] ?? '',
                    $level['sequence'] ?? 0,
                    $level['metadata'] ?? []
                );
            }
        }
        
        // 按序号排序
        usort($result, fn($a, $b) => $a->sequence <=> $b->sequence);
        
        return $result;
    }

    /**
     * 获取条件评估策略
     * 
     * @return string all: 所有条件都满足, any: 任意条件满足
     */
    public function getConditionStrategy(): string
    {
        return $this->get('condition_strategy', 'all');
    }

    /**
     * 获取是否记录历史里程碑
     * 
     * 注意：里程碑历史记录是强制的，不可配置
     *
     * @return bool
     */
    public function recordHistory(): bool
    {
        // 强制返回 true，必须记录历史
        return true;
    }

    /**
     * 获取自定义配置
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getCustomConfig(string $key, mixed $default = null): mixed
    {
        return $this->get("custom.{$key}", $default);
    }

    /**
     * 获取数据提供者注册表
     *
     * @return DataProviderRegistry
     */
    public function getDataProviderRegistry(): DataProviderRegistry
    {
        if (!$this->has('data_provider_registry')) {
            $this->set('data_provider_registry', new DataProviderRegistry());
        }
        
        return $this->get('data_provider_registry');
    }

    /**
     * 设置数据提供者注册表
     *
     * @param DataProviderRegistry $registry
     * @return static
     */
    public function setDataProviderRegistry(DataProviderRegistry $registry): static
    {
        $this->set('data_provider_registry', $registry);
        return $this;
    }

    /**
     * 获取是否记录跳过的里程碑
     * 
     * 当用户直接达到更高等级时，是否也记录中间跳过的等级
     *
     * @return bool
     */
    public function recordSkippedMilestones(): bool
    {
        return $this->get('record_skipped_milestones', true);
    }
}