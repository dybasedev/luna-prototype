<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 单位转换规则模型
 * 
 * @property int $id
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
class UnitConversionRule extends Model
{
    protected $table = 'luna_unit_conversion_rules';
    
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
}