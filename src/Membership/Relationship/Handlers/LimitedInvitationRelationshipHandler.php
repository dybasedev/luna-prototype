<?php

namespace Dybasedev\LunaPrototype\Membership\Relationship\Handlers;

/**
 * 有深度限制的邀请关系处理器
 * 
 * 用于测试最大深度限制功能
 */
class LimitedInvitationRelationshipHandler extends InvitationRelationshipHandler
{
    /**
     * 获取关系类型唯一标识
     * 
     * @return string
     */
    public function getTypeKey(): string
    {
        return 'limited-invitation';
    }

    /**
     * 获取关系类型显示名称
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return '限制深度的邀请注册';
    }

    /**
     * 获取最大层级深度
     * 
     * @return int
     */
    public function getMaxDepth(): int
    {
        return 2; // 最多两级
    }
}