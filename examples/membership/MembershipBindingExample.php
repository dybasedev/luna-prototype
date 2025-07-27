<?php

namespace Examples\Membership;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\LunaSessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\MembershipBinding;
use Illuminate\Database\Eloquent\Model;

/**
 * MembershipBinding 使用示例
 * 
 * 展示如何配置会员绑定以支持不同的数据源
 */

// 示例1：使用 Eloquent 模型
class User extends Model implements SessionHolder
{
    use LunaSessionHolder;
    
    protected $table = 'users';
    
    public function getOperatorTypeName(): string
    {
        return 'user';
    }
    
    public function getSessionHolderContext(): ?array
    {
        return null;
    }
}

// 示例2：使用自定义表的会员模型
class Customer extends Model implements SessionHolder
{
    use LunaSessionHolder;
    
    protected $table = 'customers';
    
    public function getOperatorTypeName(): string
    {
        return 'customer';
    }
    
    public function getSessionHolderContext(): ?array
    {
        return ['type' => 'customer'];
    }
}

/**
 * 配置示例
 */
class ConfigurationExample
{
    public function configureWithModels()
    {
        return LunaMembershipConfigure::create()
            ->withRelationship()
            // 绑定使用 Eloquent 模型的用户
            ->bind(
                MembershipBinding::create(User::class)
                    ->table(User::class)  // 指定使用模型类
            )
            // 绑定使用自定义表的客户
            ->bind(
                MembershipBinding::create(Customer::class)
                    ->table(Customer::class)
            )
            ->build();
    }
    
    public function configureWithTables()
    {
        return LunaMembershipConfigure::create()
            ->withRelationship()
            // 绑定直接使用表名（非模型类）
            ->bind(
                MembershipBinding::create(User::class)
                    ->table('users')  // 直接指定表名
                    ->keyName('user_id')  // 自定义主键名
            )
            ->build();
    }
    
    public function usage()
    {
        $configure = $this->configureWithModels();
        
        // 在 RelationshipManager 中，系统会：
        // 1. 根据 SessionHolder 的 getOperatorType() 查找对应的绑定
        // 2. 如果绑定使用模型类，通过 Model::find() 或 Model::whereIn() 查询
        // 3. 如果绑定使用表名，通过 DB::table() 查询
        
        // 这样可以支持：
        // - 标准的 Eloquent 模型
        // - 使用自定义表结构的旧系统
        // - 不同的主键命名约定
        // - 跨数据库的会员关系管理
    }
}