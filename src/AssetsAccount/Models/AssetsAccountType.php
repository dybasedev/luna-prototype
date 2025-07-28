<?php

namespace Dybasedev\LunaPrototype\AssetsAccount\Models;

use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 *
 * @property int $id 非自增，根据名称通过 hashcode 得出，避免迁移数据导致的关联错误
 * @property int $parent_id 父级账户类型 ID
 * @property string $name
 * @property string $display_name
 * @property string $description
 * @property int $handler_id
 * @property array<array-key, mixed> $config
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AssetsAccountType> $children
 * @property-read int|null $children_count
 * @property-read Handler|null $handler
 * @property-read AssetsAccountType|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereHandlerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetsAccountType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AssetsAccountType extends Model implements Backupable
{
    use NamedId, WithModelHandler, BackupableModel;

    protected $table = 'luna_assets_account_types';

    /**
     * @throws BindingResolutionException
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaAssetsAccountConfigure::class)->accountTypeModel,
            'parent_id', 'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function children(): HasMany
    {
        return $this->hasMany(luna_module_configure(LunaAssetsAccountConfigure::class)->accountTypeModel, 'parent_id',
            'id');
    }

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * 获取备份数据的依赖关系
     * 
     * @return array<class-string<Backupable>>
     */
    public static function getBackupableDependencies(): array
    {
        return [
            Handler::class, // 账户类型依赖处理器
        ];
    }
}