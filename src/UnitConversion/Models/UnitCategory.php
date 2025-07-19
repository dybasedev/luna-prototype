<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 单位类别模型
 * 
 * @property int $id
 * @property string $name 类别名称（currency, length, weight等）
 * @property string $display_name 显示名称
 * @property string|null $description 描述
 * @property array|null $config 配置
 * @property bool $is_active 是否启用
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Collection<UnitDefinition> $units
 */
class UnitCategory extends Model
{
    use NamedId;
    
    protected $table = 'luna_unit_categories';
    
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'config',
        'is_active',
    ];
    
    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];
    
    /**
     * 获取该类别下的所有单位定义
     */
    public function units(): HasMany
    {
        return $this->hasMany(UnitDefinition::class, 'category_id');
    }
    
    /**
     * 获取基准单位
     */
    public function baseUnit(): ?UnitDefinition
    {
        return $this->units()->where('is_base', true)->first();
    }
    
    /**
     * 获取活跃的单位
     */
    public function activeUnits(): HasMany
    {
        return $this->units()->where('is_active', true);
    }
    
    /**
     * 通过名称查找类别
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }
    
    /**
     * 通过名称查找或创建类别
     */
    public static function findOrCreateByName(string $name, array $attributes = []): self
    {
        return static::firstOrCreate(
            ['name' => $name],
            array_merge([
                'display_name' => ucfirst($name),
                'is_active' => true,
            ], $attributes)
        );
    }
}