<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\VersionControlModel\VersionControl;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property int $group_id 配置组ID
 * @property string $display_name 显示名
 * @property string $description 配置描述
 * @property string|null $current_version_id 当前版本ID，为空则无数据
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConfigurationValue|null $current
 * @property-read Collection<int, ConfigurationValue> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereCurrentVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Configuration extends Model implements Backupable
{
    use VersionControl, NamedId, BackupableModel;

    public $table = 'luna_configurations';

    protected $fillable = [
        'name',
        'group_id',
        'display_name',
        'description',
        'current_version_id',
    ];

    public function versionValueModel(): string
    {
        return ConfigurationValue::class;
    }

    /**
     * 备份数据迭代器
     * 重写以包含版本数据
     * 
     * @return \Iterator<array>
     */
    public static function backupDatasourceIterator(): \Iterator
    {
        $data = [];
        
        static::query()->with('versions')->chunk(100, function ($configurations) use (&$data) {
            foreach ($configurations as $configuration) {
                $configData = $configuration->toArray();
                
                // 包含所有版本数据
                $configData['_versions'] = $configuration->versions->map(function ($version) {
                    return [
                        'version_id' => $version->version_id,
                        'value' => $version->value,
                        'created_at' => $version->created_at?->toIso8601String(),
                    ];
                })->toArray();
                
                $data[] = $configData;
            }
        });
        
        return new \ArrayIterator($data);
    }

    /**
     * 恢复数据
     * 重写以处理版本数据
     * 
     * @param \Iterator $backup
     * @return void
     */
    public static function recoverFromBackupIterator(\Iterator $backup): void
    {
        \DB::transaction(function () use ($backup) {
            foreach ($backup as $data) {
                $versions = $data['_versions'] ?? [];
                unset($data['_versions']);
                
                // 恢复配置主体
                $attributes = $data;
                unset($attributes['created_at'], $attributes['updated_at']);
                
                $configuration = static::updateOrCreate(
                    ['name' => $data['name']],
                    $attributes
                );
                
                // 恢复版本
                foreach ($versions as $versionData) {
                    ConfigurationValue::updateOrCreate(
                        [
                            'version_id' => $versionData['version_id'],
                            'index_id' => $configuration->id,
                        ],
                        [
                            'value' => $versionData['value'],
                        ]
                    );
                }
                
                // 更新当前版本指向
                if (isset($data['current_version_id'])) {
                    $configuration->current_version_id = $data['current_version_id'];
                    $configuration->save();
                }
            }
        });
    }
}