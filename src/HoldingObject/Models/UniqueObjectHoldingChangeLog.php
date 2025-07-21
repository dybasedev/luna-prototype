<?php

namespace Dybasedev\LunaPrototype\HoldingObject\Models;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 唯一对象持有变动日志模型
 *
 * @property int $id
 * @property int $holding_id 对象ID
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property float $change_quantity 变动数量
 * @property float $before_quantity 变动前数量
 * @property int $change_status 变更状态
 * @property int $before_status 变更前状态
 * @property int $event_id 事件ID
 * @property array $payload 载荷
 * @property \Illuminate\Support\Carbon|null $expired_at 过期时间
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read UniqueObjectHolding $holding
 * @property-read BusinessEvent|null $event
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereBeforeQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereBeforeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereChangeQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereChangeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereExpiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereHoldingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHoldingChangeLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class UniqueObjectHoldingChangeLog extends Model
{
    protected $table = 'luna_global_unique_object_holding_change_logs';

    protected $fillable = [
        'holding_id',
        'owner_id',
        'owner_type',
        'change_quantity',
        'before_quantity',
        'change_status',
        'before_status',
        'event_id',
        'payload',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'holding_id' => 'integer',
            'owner_id' => 'integer',
            'owner_type' => 'integer',
            'change_quantity' => 'decimal:8',
            'before_quantity' => 'decimal:8',
            'change_status' => 'integer',
            'before_status' => 'integer',
            'event_id' => 'integer',
            'payload' => 'array',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * 获取关联的持有记录
     *
     * @return BelongsTo
     */
    public function holding(): BelongsTo
    {
        return $this->belongsTo(UniqueObjectHolding::class, 'holding_id');
    }

    /**
     * 获取关联的业务事件
     *
     * @return BelongsTo
     * @throws BindingResolutionException
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaBusinessEventConfigure::class)->model, 'event_id', 'id');
    }
}