<?php

namespace Dybasedev\LunaPrototype\Membership;

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Membership\Milestone\LunaMilestone;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Luna 会员系统管理对象
 *
 * 这是 Luna 框架中的会员系统管理类，提供了会员体系的基础框架和扩展支持。
 * 该模块不提供具体的会员管理功能，而是为会员体系提供必要的基础设施和扩展点。
 *
 * 主要功能：
 * - 会员等级管理框架
 * - 会员里程碑系统
 * - 会员权益管理接口
 * - 会员数据绑定支持
 * - 会员升级机制基础
 *
 * 设计理念：
 * - 提供灵活的会员体系框架
 * - 支持多种会员等级模式
 * - 集成缓存提高性能
 * - 支持扩展和定制
 * - 与其他模块无缝集成
 *
 * @package Dybasedev\LunaPrototype\Membership
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaMembership extends LunaModule
{
    /**
     * 里程碑管理实例
     *
     * @var LunaMilestone|null
     */
    protected ?LunaMilestone $milestone = null;

    /**
     * 会员系统管理对象构造函数
     *
     * @param LunaMembershipConfigure $configure 会员系统配置对象
     * @param Cache $cache 缓存接口实例
     * @param LunaHandler $handler 处理器管理器
     */
    public function __construct(
        protected(set) LunaMembershipConfigure $configure,
        protected Cache $cache,
        protected LunaHandler $handler
    )
    {
    }

    /**
     * 获取里程碑管理实例
     *
     * @return LunaMilestone|null 如果未启用里程碑功能返回 null
     */
    public function milestone(): ?LunaMilestone
    {
        if (!$this->configure->isMilestoneEnabled()) {
            return null;
        }

        if (!$this->milestone) {
            $this->milestone = new LunaMilestone(
                $this->configure,
                $this->cache,
                $this->handler
            );
        }

        return $this->milestone;
    }

    /**
     * 获取会员绑定配置列表
     *
     * @return array<MembershipBinding>
     */
    public function getBindings(): array
    {
        return $this->configure->bindings;
    }

    /**
     * 检查是否启用了里程碑功能
     *
     * @return bool
     */
    public function isMilestoneEnabled(): bool
    {
        return $this->configure->isMilestoneEnabled();
    }
}