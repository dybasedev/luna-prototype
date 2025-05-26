<?php

namespace Dybasedev\LunaPrototype\AssetsAccount\Models;

use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessConfigure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property int $account_id 账户ID
 * @property int $account_type_id 账户类型ID
 * @property string $change_value 变动金额
 * @property string $before_value 变动前余额
 * @property int $change_type 变动类型
 * @property int $event_id 事件ID
 * @property string $payload 载荷
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AssetsAccount|null $account
 * @property-read AssetsAccountType|null $accountType
 * @property-read BusinessEvent|null $event
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereBeforeValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereChangeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereChangeValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountChangeLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AssetsAccountChangeLog extends Model
{
    protected $table = 'luna_assets_account_change_logs';

    /**
     * @throws BindingResolutionException
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaAssetsAccountConfigure::class)->accountModel, 'account_id', 'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaAssetsAccountConfigure::class)->accountTypeModel, 'account_type_id', 'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaBusinessConfigure::class)->model, 'event_id', 'id');
    }
}