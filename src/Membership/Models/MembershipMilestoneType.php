<?php

namespace Dybasedev\LunaPrototype\Membership\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 会员里程碑类型模型
 * 
 * @property int $id 非自增ID，通过名称的hashcode生成
 * @property string $name 类型名称（唯一）
 * @property string $display_name 显示名称
 * @property string $description 描述
 * @property int $handler_id 处理器ID
 * @property array $config 配置信息
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<MembershipMilestone> $milestones
 */
class MembershipMilestoneType extends Model implements Backupable
{
    use NamedId, BackupableModel;
    
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_membership_milestone_types';

    /**
     * 可批量赋值的属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'name',
        'display_name',
        'description',
        'handler_id',
        'config',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'handler_id' => 'integer',
        'config' => 'array',
    ];

    /**
     * 获取该类型的所有里程碑
     *
     * @return HasMany
     * @throws BindingResolutionException
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaMembershipConfigure::class)->milestoneModel,
            'milestone_type_id'
        );
    }

    /**
     * 获取该类型的所有里程碑日志
     *
     * @return HasMany
     * @throws BindingResolutionException
     */
    public function logs(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaMembershipConfigure::class)->milestoneLogModel,
            'milestone_type_id'
        );
    }

    /**
     * 根据名称查找或创建里程碑类型
     *
     * @param string $name
     * @param array $attributes
     * @return static
     */
    public static function findOrCreateByName(string $name, array $attributes = []): static
    {
        return static::query()->firstOrCreate(
            ['name' => $name],
            array_merge(['name' => $name], $attributes)
        );
    }

    /**
     * 获取备份数据的依赖关系
     * 
     * @return array<class-string<Backupable>>
     */
    public static function getBackupableDependencies(): array
    {
        return [
            \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler::class, // 里程碑类型依赖处理器
        ];
    }
}