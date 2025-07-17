<?php

namespace Dybasedev\LunaPrototype\Membership;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;

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
     * 获取模块名称
     *
     * @return string 会员系统模块的标识名称
     */
    public function name(): string
    {
        return 'luna.membership';
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

}