<?php

namespace Dybasedev\LunaPrototype\Membership\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 会员关系索引模型
 * 
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property int $relationship_type 关系类型
 * @property int $left_value 左值
 * @property int $right_value 右值
 * @property int $depth 深度
 */
class MembershipRelationshipIndex extends Model
{
    /**
     * 表名
     * 
     * @var string
     */
    protected $table = 'luna_membership_relationship_indices';

    /**
     * 不使用时间戳
     * 
     * @var bool
     */
    public $timestamps = false;

    /**
     * 主键设置
     * 
     * @var string
     */
    protected $primaryKey = null;

    /**
     * 不递增主键
     * 
     * @var bool
     */
    public $incrementing = false;

    /**
     * 可填充字段
     * 
     * @var array
     */
    protected $fillable = [
        'owner_id',
        'owner_type',
        'relationship_type',
        'left_value',
        'right_value',
        'depth',
    ];

    /**
     * 类型转换
     * 
     * @var array
     */
    protected $casts = [
        'owner_id' => 'integer',
        'owner_type' => 'integer',
        'relationship_type' => 'integer',
        'left_value' => 'integer',
        'right_value' => 'integer',
        'depth' => 'integer',
    ];

    /**
     * 获取所有者模型
     * 
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function owner()
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    /**
     * 判断是否为根节点
     * 
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->depth === 0;
    }

    /**
     * 判断是否为叶子节点
     * 
     * @return bool
     */
    public function isLeaf(): bool
    {
        return $this->right_value - $this->left_value === 1;
    }

    /**
     * 获取子节点数量
     * 
     * @return int
     */
    public function getChildrenCount(): int
    {
        return ($this->right_value - $this->left_value - 1) / 2;
    }

    /**
     * 判断是否为另一个节点的祖先
     * 
     * @param MembershipRelationshipIndex $other
     * @return bool
     */
    public function isAncestorOf(MembershipRelationshipIndex $other): bool
    {
        return $this->left_value < $other->left_value && $this->right_value > $other->right_value;
    }

    /**
     * 判断是否为另一个节点的后代
     * 
     * @param MembershipRelationshipIndex $other
     * @return bool
     */
    public function isDescendantOf(MembershipRelationshipIndex $other): bool
    {
        return $this->left_value > $other->left_value && $this->right_value < $other->right_value;
    }
}