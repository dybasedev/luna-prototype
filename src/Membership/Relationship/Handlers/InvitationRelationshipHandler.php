<?php

namespace Dybasedev\LunaPrototype\Membership\Relationship\Handlers;

use Dybasedev\LunaPrototype\Membership\Relationship\RelationshipHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 邀请注册关系处理器
 * 
 * 处理用户之间的邀请注册关系
 */
class InvitationRelationshipHandler extends RelationshipHandler
{
    /**
     * 获取关系类型唯一标识
     * 
     * @return string
     */
    public function getTypeKey(): string
    {
        return 'invitation';
    }

    /**
     * 获取关系类型显示名称
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return '邀请注册';
    }

    /**
     * 获取关系类型描述
     * 
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return '用户通过邀请链接注册建立的推荐关系';
    }

    /**
     * 是否允许修改关系
     * 
     * @return bool
     */
    public function allowsModification(): bool
    {
        // 邀请关系一旦建立不允许修改
        return false;
    }

    /**
     * 加入关系链前的验证
     * 
     * @param SessionHolder $parent 邀请人
     * @param SessionHolder $child 被邀请人
     * @param array $context 上下文数据
     * @return bool
     */
    public function validateJoin(SessionHolder $parent, SessionHolder $child, array $context = []): bool
    {
        // 不能邀请自己
        if ($parent->getOperatorId() === $child->getOperatorId() && 
            $parent->getOperatorType() === $child->getOperatorType()) {
            return false;
        }

        // 可以添加更多验证逻辑，如：
        // - 邀请人是否有邀请资格
        // - 被邀请人是否是新用户
        // - 是否在活动期间内

        return true;
    }

    /**
     * 加入关系链时的处理逻辑
     * 
     * @param SessionHolder $parent 邀请人
     * @param SessionHolder $child 被邀请人
     * @param array $context 上下文数据
     * @return void
     */
    public function onJoin(SessionHolder $parent, SessionHolder $child, array $context = []): void
    {
        // 可以在这里实现业务逻辑，如：
        // - 给邀请人发放奖励
        // - 记录邀请事件
        // - 触发相关业务事件
        
        // 示例：触发业务事件
        // if (function_exists('luna_business_event')) {
        //     luna_business_event()->fire('user-invitation-success', [
        //         'inviter_id' => $parent->getOperatorId(),
        //         'invitee_id' => $child->getOperatorId(),
        //         'invitation_code' => $context['invitation_code'] ?? null,
        //     ]);
        // }
    }
}