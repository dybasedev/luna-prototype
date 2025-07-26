<?php

namespace Dybasedev\LunaPrototype\Membership;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestone;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneLog;
use Dybasedev\LunaPrototype\Membership\Models\MembershipMilestoneType;
use Dybasedev\LunaPrototype\Membership\Models\MembershipRelationshipIndex;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 会员系统配置类
 *
 * 负责配置 Luna 会员系统的各项设置，包括会员绑定、权益配置等。
 * 这个类扩展了 LunaModuleConfigure，为会员系统提供了专门的配置选项。
 *
 * 主要配置内容：
 * - 会员数据绑定配置
 * - 会员权益设置
 * - 会员等级管理
 * - 系统服务注册
 *
 * @package Dybasedev\LunaPrototype\Membership
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaMembershipConfigure extends LunaModuleConfigure
{
    /**
     * 会员绑定配置列表
     *
     * 存储会员系统与其他实体的绑定关系配置。
     * 这些绑定定义了会员系统如何与用户、订单等实体关联。
     *
     * @var MembershipBinding[]
     */
    protected(set) array $bindings = [];

    /**
     * @var class-string<MembershipMilestoneType>
     */
    protected(set) string $milestoneTypeModel = MembershipMilestoneType::class;

    /**
     * @var class-string<MembershipMilestone>
     */
    protected(set) string $milestoneModel = MembershipMilestone::class;

    /**
     * @var class-string<MembershipMilestoneLog>
     */
    protected(set) string $milestoneLogModel = MembershipMilestoneLog::class;

    /**
     * @var class-string<MembershipRelationshipIndex>
     */
    protected(set) string $relationshipIndexModel = MembershipRelationshipIndex::class;

    /**
     * 是否启用里程碑功能
     *
     * @var bool
     */
    protected(set) bool $enableMilestone = true;

    /**
     * 是否启用关系管理功能
     *
     * @var bool
     */
    protected(set) bool $enableRelationship = true;

    /**
     * 获取模块名称
     *
     * @return string 会员系统模块的标识名称
     */
    public function name(): string
    {
        return 'luna.membership';
    }

    /**
     * 获取服务提供者
     *
     * @return string|null
     */
    public function serviceProvider(): ?string
    {
        return LunaMembershipServiceProvider::class;
    }

    /**
     * 注册模块服务
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.membership', function($app) {
            return new LunaMembership(
                $app->make(LunaMembershipConfigure::class),
                $app->make('cache'),
                $app->make(LunaHandler::class)
            );
        });

        $container->alias('luna.membership', LunaMembership::class);
    }

    /**
     * 启动模块
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // 如果启用了里程碑功能，自动注册会员里程碑处理器组
        if ($this->enableMilestone) {
            $container->make(LunaHandlerConfigure::class)->group('membership-milestones', '会员里程碑');
        }

        // 如果启用了关系管理功能，自动注册会员关系类型处理器组
        if ($this->enableRelationship) {
            $container->make(LunaHandlerConfigure::class)->group('membership-relationships', '会员关系类型');
        }
    }

    /**
     * 替换默认的里程碑类型模型
     *
     * @param class-string<MembershipMilestoneType> $class
     * @return $this
     */
    public function useMilestoneTypeModel(string $class): static
    {
        $this->milestoneTypeModel = $class;
        return $this;
    }

    /**
     * 替换默认的里程碑模型
     *
     * @param class-string<MembershipMilestone> $class
     * @return $this
     */
    public function useMilestoneModel(string $class): static
    {
        $this->milestoneModel = $class;
        return $this;
    }

    /**
     * 替换默认的里程碑日志模型
     *
     * @param class-string<MembershipMilestoneLog> $class
     * @return $this
     */
    public function useMilestoneLogModel(string $class): static
    {
        $this->milestoneLogModel = $class;
        return $this;
    }

    /**
     * 添加会员绑定配置
     *
     * 向会员系统添加新的绑定配置，定义会员与其他实体的关联关系。
     *
     * @param MembershipBinding $binding 会员绑定配置对象
     * @return $this 返回当前实例以支持链式调用
     */
    public function bind(MembershipBinding $binding): static
    {
        $this->bindings[] = $binding;
        return $this;
    }

    /**
     * 启用里程碑功能
     *
     * @return $this
     */
    public function withMilestone(): static
    {
        $this->enableMilestone = true;
        return $this;
    }

    /**
     * 禁用里程碑功能
     *
     * @return $this
     */
    public function withoutMilestone(): static
    {
        $this->enableMilestone = false;
        return $this;
    }

    /**
     * 是否启用了里程碑功能
     *
     * @return bool
     */
    public function isMilestoneEnabled(): bool
    {
        return $this->enableMilestone;
    }

    /**
     * 替换默认的关系索引模型
     *
     * @param class-string<MembershipRelationshipIndex> $class
     * @return $this
     */
    public function useRelationshipIndexModel(string $class): static
    {
        $this->relationshipIndexModel = $class;
        return $this;
    }

    /**
     * 启用关系管理功能
     *
     * @return $this
     */
    public function withRelationship(): static
    {
        $this->enableRelationship = true;
        return $this;
    }

    /**
     * 禁用关系管理功能
     *
     * @return $this
     */
    public function withoutRelationship(): static
    {
        $this->enableRelationship = false;
        return $this;
    }

    /**
     * 是否启用了关系管理功能
     *
     * @return bool
     */
    public function isRelationshipEnabled(): bool
    {
        return $this->enableRelationship;
    }

}