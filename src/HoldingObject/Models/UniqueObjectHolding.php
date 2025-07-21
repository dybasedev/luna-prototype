<?php

namespace Dybasedev\LunaPrototype\HoldingObject\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 唯一对象持有模型
 *
 * @property int $id
 * @property int $object_type 对象类型，注册类型 name 通过 hash_code 获得
 * @property int $object_id 对象ID
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property bool $exists_extended 是否存在扩展信息
 * @property array $payload 载荷
 * @property int $status 状态
 * @property float $quantity 数量
 * @property int|null $unit_id 数量单位 ID
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $owner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereExistsExtended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereObjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereObjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UniqueObjectHolding whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class UniqueObjectHolding extends Model
{
    protected $table = 'luna_global_unique_object_holdings';

    protected $fillable = [
        'object_type',
        'object_id',
        'owner_id',
        'owner_type',
        'exists_extended',
        'payload',
        'status',
        'quantity',
        'unit_id',
    ];

    protected function casts(): array
    {
        return [
            'object_type' => 'integer',
            'object_id' => 'string',
            'owner_id' => 'integer',
            'owner_type' => 'integer',
            'exists_extended' => 'boolean',
            'payload' => 'array',
            'status' => 'integer',
            'quantity' => 'decimal:8',
            'unit_id' => 'integer',
        ];
    }

    /**
     * 获取所有者
     *
     * @return MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }
}