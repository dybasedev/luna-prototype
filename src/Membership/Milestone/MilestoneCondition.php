<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 里程碑达成条件接口
 * 
 * 用于定义里程碑达成的条件判断逻辑
 */
interface MilestoneCondition
{
    /**
     * 判断是否满足条件
     *
     * @param SessionHolder $owner 里程碑所有者（如会员）
     * @param array $context 判断上下文（包含相关数据）
     * @return bool
     */
    public function isSatisfied(SessionHolder $owner, array $context = []): bool;

    /**
     * 获取条件的唯一标识
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * 获取条件描述
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * 获取条件配置
     *
     * @return array
     */
    public function getConfig(): array;
}