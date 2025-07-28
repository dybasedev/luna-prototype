<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models;

use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id 非自增，根据名称通过 hashcode 得出，避免迁移数据导致的关联错误
 * @property int $group_id 分组 ID，为 0 表示通用
 * @property string $name
 * @property string $display_name
 * @property string $formatter 事件信息格式表达式，会由具体的 handler 负责解析，若没有提供 handler 则以默认解析器解析
 * @property int|null $handler_id
 * @property array<array-key, mixed> $config
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Handler|null $handler
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereFormatter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereHandlerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessEvent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BusinessEvent extends Model implements Backupable
{
    use NamedId, WithModelHandler, BackupableModel;

    protected $table = 'luna_business_events';

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
            Handler::class, // 业务事件可能依赖处理器
        ];
    }
}