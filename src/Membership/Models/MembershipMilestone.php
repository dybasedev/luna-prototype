<?php

namespace Dybasedev\LunaPrototype\Membership\Models;

use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 会员里程碑模型
 * 
 * @property int $id
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property int $milestone_type_id 里程碑类型ID
 * @property int $milestone 里程碑标识符的hash值
 * @property array $payload 额外数据
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read MembershipMilestoneType $milestoneType
 * @property-read \Illuminate\Database\Eloquent\Collection<MembershipMilestoneLog> $logs
 */
class MembershipMilestone extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_membership_milestones';

    /**
     * 可批量赋值的属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'owner_id',
        'owner_type',
        'milestone_type_id',
        'milestone',
        'payload',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'owner_type' => 'integer',
        'milestone_type_id' => 'integer',
        'milestone' => 'integer',
    ];

    /**
     * 获取里程碑类型
     *
     * @return BelongsTo
     * @throws BindingResolutionException
     */
    public function milestoneType(): BelongsTo
    {
        return $this->belongsTo(
            luna_module_configure(LunaMembershipConfigure::class)->milestoneTypeModel,
            'milestone_type_id'
        );
    }

    /**
     * 获取里程碑变更日志
     *
     * @return HasMany
     * @throws BindingResolutionException
     */
    public function logs(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaMembershipConfigure::class)->milestoneLogModel,
            'owner_id',
            'owner_id'
        )
            ->where('owner_type', $this->owner_type)
            ->where('milestone_type_id', $this->milestone_type_id);
    }

    /**
     * 获取所有者模型
     *
     * @return Model|null
     */
    public function getOwner(): ?Model
    {
        // 需要实现反向查找所有者类型的逻辑
        // 这里暂时返回 null，实际使用时可能需要维护一个类型映射表
        return null;
    }

    /**
     * 检查是否为特定里程碑
     *
     * @param string $identifier
     * @return bool
     */
    public function isMilestone(string $identifier): bool
    {
        return $this->milestone === hash_code($identifier);
    }
}