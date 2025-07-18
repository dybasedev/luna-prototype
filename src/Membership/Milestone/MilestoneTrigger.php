<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 里程碑触发器接口
 * 
 * 定义了在业务系统中触发里程碑评估的标准接口
 */
interface MilestoneTrigger
{
    /**
     * 触发里程碑评估
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param string $milestoneTypeName 里程碑类型名称
     * @param array $context 触发上下文
     * @return MilestoneLevel|null 返回评估后的里程碑等级
     */
    public function trigger(SessionHolder $owner, string $milestoneTypeName, array $context = []): ?MilestoneLevel;

    /**
     * 批量触发多个里程碑类型的评估
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param array $milestoneTypeNames 里程碑类型名称数组
     * @param array $context 触发上下文
     * @return array<string, MilestoneLevel|null> 返回各类型评估结果
     */
    public function triggerMultiple(SessionHolder $owner, array $milestoneTypeNames, array $context = []): array;

    /**
     * 触发所有相关的里程碑评估
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param array $context 触发上下文
     * @return array<string, MilestoneLevel|null> 返回所有类型评估结果
     */
    public function triggerAll(SessionHolder $owner, array $context = []): array;
}