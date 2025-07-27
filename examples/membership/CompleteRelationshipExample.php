<?php

namespace Examples\Membership;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Relationship\RelationshipHandler;

/**
 * 完整的关系处理器示例
 * 
 * 展示如何使用 SessionHolder 接口创建自定义关系类型
 */
class TeamRelationshipHandler extends RelationshipHandler
{
    public function getTypeKey(): string
    {
        return 'team';
    }

    public function getDisplayName(): string
    {
        return '团队关系';
    }

    public function getDescription(): ?string
    {
        return '用于管理团队成员之间的层级关系';
    }

    /**
     * 验证是否可以加入团队
     * 
     * @param SessionHolder $parent 上级成员（团队负责人或上级）
     * @param SessionHolder $child 下级成员（新成员）
     * @param array $context 上下文，可能包含 role、permissions 等
     * @return bool
     */
    public function validateJoin(SessionHolder $parent, SessionHolder $child, array $context = []): bool
    {
        // 不能添加自己为下级
        if ($parent->getOperatorId() === $child->getOperatorId() && 
            $parent->getOperatorType() === $child->getOperatorType()) {
            return false;
        }

        // 检查操作者类型是否相同（例如都是 user 类型）
        if ($parent->getOperatorTypeName() !== $child->getOperatorTypeName()) {
            return false;
        }

        // 可以根据上下文信息进行更多验证
        $parentContext = $parent->getSessionHolderContext();
        $childContext = $child->getSessionHolderContext();

        // 例如：检查父级是否有邀请权限
        if (isset($parentContext['permissions']) && 
            !in_array('invite_members', $parentContext['permissions'])) {
            return false;
        }

        // 例如：检查子级是否已经在其他团队
        if (isset($childContext['team_id']) && $childContext['team_id'] !== null) {
            return false;
        }

        return true;
    }

    /**
     * 加入团队时的处理
     */
    public function onJoin(SessionHolder $parent, SessionHolder $child, array $context = []): void
    {
        // 可以触发业务事件
        // luna_business_event()->fire('team-member-joined', [
        //     'team_leader_id' => $parent->getOperatorId(),
        //     'team_leader_type' => $parent->getOperatorTypeName(),
        //     'member_id' => $child->getOperatorId(),
        //     'member_type' => $child->getOperatorTypeName(),
        //     'role' => $context['role'] ?? 'member',
        // ]);

        // 记录审计日志
        logger()->info('Team member joined', [
            'parent' => [
                'id' => $parent->getOperatorId(),
                'type' => $parent->getOperatorTypeName(),
            ],
            'child' => [
                'id' => $child->getOperatorId(),
                'type' => $child->getOperatorTypeName(),
            ],
            'context' => $context,
        ]);
    }

    /**
     * 最大深度限制为 5 级
     */
    public function getMaxDepth(): int
    {
        return 5;
    }

    /**
     * 允许修改团队关系（可以更换上级）
     */
    public function allowsModification(): bool
    {
        return true;
    }
}

/**
 * 使用示例
 */
class UsageExample
{
    public function example()
    {
        // 假设有这些 SessionHolder 实例
        // $ceo = User::find(1);      // CEO
        // $manager = User::find(2);   // 经理
        // $employee = User::find(3);  // 员工
        
        // 注册处理器
        // $configure = LunaHandlerConfigure::create()
        //     ->group('membership-relationships', '会员关系类型', function ($register) {
        //         $register->handler(TeamRelationshipHandler::class);
        //     })
        //     ->build();
        
        // 创建关系
        // $relationship = $membershipManager->createRelationship(
        //     'team',
        //     $ceo,      // SessionHolder
        //     $manager,  // SessionHolder
        //     ['role' => 'department_manager']
        // );
        
        // 查询团队成员
        // $teamMembers = $membershipManager->getDescendants('team', $ceo);
        
        // 查询直接下属
        // $directReports = $membershipManager->getChildren('team', $manager);
    }
}