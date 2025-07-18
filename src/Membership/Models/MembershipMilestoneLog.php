<?php

namespace Dybasedev\LunaPrototype\Membership\Models;

use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneLevel;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 会员里程碑变更日志模型
 * 
 * @property int $id
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property int $milestone_type_id 里程碑类型ID
 * @property int $milestone 达到的里程碑标识符hash值
 * @property int|null $before_milestone 变更前的里程碑标识符hash值
 * @property array $payload 额外数据
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read MembershipMilestoneType $milestoneType
 */
class MembershipMilestoneLog extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_membership_milestone_logs';

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
        'before_milestone',
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
        'before_milestone' => 'integer',
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
     * 检查是否为特定里程碑
     *
     * @param string $identifier
     * @return bool
     */
    public function isMilestone(string $identifier): bool
    {
        return $this->milestone === hash_code($identifier);
    }

    /**
     * 检查变更前是否为特定里程碑
     *
     * @param string $identifier
     * @return bool
     */
    public function wasFromMilestone(string $identifier): bool
    {
        return $this->before_milestone === hash_code($identifier);
    }

    /**
     * 是否为升级
     *
     * @param array<MilestoneLevel> $levels 里程碑等级定义数组
     * @return bool|null null表示无法判断
     */
    public function isUpgrade(array $levels): ?bool
    {
        if ($this->before_milestone === null) {
            return true; // 首次达到里程碑视为升级
        }

        $beforeSequence = null;
        $currentSequence = null;

        foreach ($levels as $level) {
            if (hash_code($level->identifier) === $this->before_milestone) {
                $beforeSequence = $level->sequence;
            }
            if (hash_code($level->identifier) === $this->milestone) {
                $currentSequence = $level->sequence;
            }
        }

        if ($beforeSequence === null || $currentSequence === null) {
            return null;
        }

        return $currentSequence > $beforeSequence;
    }
}