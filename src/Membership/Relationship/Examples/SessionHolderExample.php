<?php

namespace Dybasedev\LunaPrototype\Membership\Relationship\Examples;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\LunaSessionHolder;
use Illuminate\Database\Eloquent\Model;

/**
 * SessionHolder 使用示例
 * 
 * 展示如何实现 SessionHolder 接口以支持会员关系管理
 */
class User extends Model implements SessionHolder
{
    use LunaSessionHolder;
    
    protected $table = 'users';
    
    /**
     * 获取操作人类型名称
     * 
     * @return string
     */
    public function getOperatorTypeName(): string
    {
        return 'user';
    }
    
    /**
     * 获取会话持有人上下文信息
     * 
     * @return array|null
     */
    public function getSessionHolderContext(): ?array
    {
        return [
            'type' => 'user',
            'role' => $this->role ?? 'member',
            'verified' => $this->email_verified_at !== null,
        ];
    }
}

/**
 * 管理员模型示例
 */
class Admin extends Model implements SessionHolder
{
    use LunaSessionHolder;
    
    protected $table = 'admins';
    
    public function getOperatorTypeName(): string
    {
        return 'admin';
    }
    
    public function getSessionHolderContext(): ?array
    {
        return [
            'type' => 'admin',
            'permissions' => $this->permissions ?? [],
        ];
    }
}

/**
 * 使用示例
 */
class Example
{
    public function demonstrateUsage()
    {
        // 创建用户
        $inviter = new User(['id' => 1, 'name' => '邀请人']);
        $invitee = new User(['id' => 2, 'name' => '被邀请人']);
        
        // SessionHolder 接口提供的方法
        echo "邀请人类型名: " . $inviter->getOperatorTypeName() . "\n"; // 'user'
        echo "邀请人类型ID: " . $inviter->getOperatorType() . "\n";     // hash_code('user')
        echo "邀请人ID: " . $inviter->getOperatorId() . "\n";           // 1
        
        // 在 RelationshipManager 中使用
        // $relationshipManager->createRelationship('invitation', $inviter, $invitee);
        
        // 系统会使用 SessionHolder 接口方法而不是 Model 特定方法
        // 这样可以支持不同类型的操作者（用户、管理员等）
    }
}