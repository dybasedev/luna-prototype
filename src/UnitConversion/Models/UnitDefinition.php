<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 单位定义模型
 * 
 * @property int $id
 * @property int $category_id
 * @property string $code 单位代码
 * @property string|null $symbol 单位符号
 * @property string $display_name 显示名称
 * @property string|null $description 描述
 * @property int $precision 精度
 * @property float $base_value 相对于基准单位的值
 * @property bool $is_base 是否为基准单位
 * @property array|null $metadata 元数据
 * @property bool $is_active 是否启用
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read UnitCategory $category
 * @property-read \Illuminate\Database\Eloquent\Collection<UnitConversionRule> $conversionRulesFrom
 * @property-read \Illuminate\Database\Eloquent\Collection<UnitConversionRule> $conversionRulesTo
 */
class UnitDefinition extends Model
{
    protected $table = 'luna_unit_definitions';
    
    public $incrementing = false;
    
    protected $keyType = 'int';
    
    protected $fillable = [
        'id',
        'category_id',
        'code',
        'symbol',
        'display_name',
        'description',
        'precision',
        'base_value',
        'is_base',
        'metadata',
        'is_active',
    ];
    
    protected $casts = [
        'precision' => 'integer',
        'base_value' => 'float',
        'is_base' => 'boolean',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];
    
    /**
     * 获取所属类别
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(UnitCategory::class);
    }
    
    /**
     * 获取从该单位出发的转换规则
     */
    public function conversionRulesFrom(): HasMany
    {
        return $this->hasMany(UnitConversionRule::class, 'from_unit_id');
    }
    
    /**
     * 获取到该单位的转换规则
     */
    public function conversionRulesTo(): HasMany
    {
        return $this->hasMany(UnitConversionRule::class, 'to_unit_id');
    }
    
    /**
     * 判断是否可以转换到另一个单位
     */
    public function canConvertTo(UnitDefinition $targetUnit): bool
    {
        // 同类别的单位总是可以转换
        if ($this->category_id === $targetUnit->category_id) {
            return true;
        }
        
        // 检查是否有特殊转换规则
        return $this->conversionRulesFrom()
            ->where('to_unit_id', $targetUnit->id)
            ->where('is_active', true)
            ->exists();
    }
    
    /**
     * 获取到目标单位的基础转换率
     */
    public function getBaseConversionRate(UnitDefinition $targetUnit): ?float
    {
        // 同一单位
        if ($this->id === $targetUnit->id) {
            return 1.0;
        }
        
        // 同类别单位使用基准值计算
        if ($this->category_id === $targetUnit->category_id) {
            if ($this->base_value == 0) {
                return null;
            }
            return $targetUnit->base_value / $this->base_value;
        }
        
        return null;
    }
    
    /**
     * 格式化数值
     */
    public function formatValue(float $value): string
    {
        $formatted = number_format($value, $this->precision);
        
        if ($this->symbol) {
            return $this->symbol . $formatted;
        }
        
        return $formatted . ' ' . $this->code;
    }
    
    /**
     * 通过代码和类别查找单位
     */
    public static function findByCode(string $code, ?int $categoryId = null): ?self
    {
        $query = static::where('code', $code);
        
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        
        return $query->first();
    }
    
    /**
     * 生成单位ID
     * 
     * @param UnitCategory|int $category 类别对象或类别ID
     * @param string $code 单位代码
     */
    public static function generateId(UnitCategory|int $category, string $code): int
    {
        if ($category instanceof UnitCategory) {
            $categoryName = $category->name;
        } else {
            // 直接使用UnitCategory模型查找
            $cat = UnitCategory::find($category);
            $categoryName = $cat ? $cat->name : (string)$category;
        }
        
        return hash_code($categoryName . ':' . $code);
    }
    
    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function (self $model) {
            // 如果没有设置ID，则自动生成
            if (!$model->id && $model->category_id && $model->code) {
                $model->id = static::generateId($model->category_id, $model->code);
            }
        });
    }
}