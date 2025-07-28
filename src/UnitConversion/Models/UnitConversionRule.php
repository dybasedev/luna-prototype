<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Support\CompositePrimaryKey;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 单位转换规则模型
 * 
 * @property int $from_unit_id
 * @property int $to_unit_id
 * @property int $handler_id
 * @property array|null $config 转换配置
 * @property int $priority 优先级
 * @property bool $is_active 是否启用
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read UnitDefinition $fromUnit
 * @property-read UnitDefinition $toUnit
 * @property-read Handler $handler
 */
class UnitConversionRule extends Model implements Backupable
{
    use CompositePrimaryKey, BackupableModel;
    
    protected $table = 'luna_unit_conversion_rules';
    
    /**
     * 禁用自增主键
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * 复合主键
     *
     * @var array<string>
     */
    protected $primaryKey = ['from_unit_id', 'to_unit_id', 'handler_id'];
    
    protected $fillable = [
        'from_unit_id',
        'to_unit_id',
        'handler_id',
        'config',
        'priority',
        'is_active',
    ];
    
    protected $casts = [
        'config' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];
    
    /**
     * 获取源单位
     */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(UnitDefinition::class, 'from_unit_id');
    }
    
    /**
     * 获取目标单位
     */
    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(UnitDefinition::class, 'to_unit_id');
    }
    
    /**
     * 获取处理器
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Handler::class, 'handler_id');
    }
    
    /**
     * 查找适用的转换规则
     */
    public static function findApplicableRule(int $fromUnitId, int $toUnitId): ?self
    {
        return static::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * 获取备份对象的关联键配置
     * 使用复合主键作为唯一标识
     * 
     * @return string|array|null
     */
    public static function getBackupableRelationKey(): string|array|null
    {
        return ['from_unit_id', 'to_unit_id', 'handler_id'];
    }

    /**
     * 获取备份数据的依赖关系
     * 
     * @return array<class-string<Backupable>>
     */
    public static function getBackupableDependencies(): array
    {
        return [
            UnitDefinition::class, // 转换规则依赖单位定义
            Handler::class, // 转换规则依赖处理器
        ];
    }
}