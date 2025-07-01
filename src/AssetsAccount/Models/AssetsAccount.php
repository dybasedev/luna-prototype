<?php

namespace Dybasedev\LunaPrototype\AssetsAccount\Models;

use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 *
 * @property int $id
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property int $parent_id 父级ID
 * @property int $account_type_id 账户类型ID
 * @property string $available_balance 可用余额
 * @property string $frozen_balance 冻结余额
 * @property string $locked_balance 锁定余额
 * @property int $status 状态
 * @property int $exists_extended 是否存在扩展信息
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AssetsAccountType|null $accountType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AssetsAccount> $children
 * @property-read int|null $children_count
 * @property-read AssetsAccount|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereAvailableBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereExistsExtended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereFrozenBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereLockedBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AssetsAccount extends Model
{
    protected $table = 'luna_assets_accounts';

    /**
     * @throws BindingResolutionException
     */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaAssetsAccountConfigure::class)->accountTypeModel,
            'account_type_id', 'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaAssetsAccountConfigure::class)->accountModel, 'parent_id',
            'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function children(): HasMany
    {
        return $this->hasMany(luna_module_configure(LunaAssetsAccountConfigure::class)->accountModel, 'parent_id',
            'id');
    }

    /**
     * 是否存在子账户
     *
     * @return bool
     */
    public function existsChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->count() > 0;
        }

        return static::query()
            ->where('owner_id', $this->owner_id)
            ->where('owner_type', $this->owner_type)
            ->where('parent_id', $this->id)
            ->exists();
    }
}