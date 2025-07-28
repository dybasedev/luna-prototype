<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Model;

/**
 * 处理器模型
 *
 * @property int $id 非自增，根据名称通过 hashcode 得出，避免迁移数据导致的关联错误
 * @property string $name 处理器名称
 * @property int $group_id 分组ID，由代码定义
 * @property string $display_name 显示名
 * @property string $description 描述
 * @property string $handler 处理器类名
 * @property array $config 默认配置
 * @property bool $enabled 是否启用
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereHandler($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Handler whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Handler extends Model implements Backupable
{
    use NamedId, BackupableModel;

    protected $table = 'luna_handlers';

    protected $fillable = [
        'name',
        'group_id',
        'display_name',
        'description',
        'handler',
        'config',
        'enabled',
    ];

    protected function casts():array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }
}