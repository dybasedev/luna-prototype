<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Attributes;

use Illuminate\Contracts\Support\Arrayable;

/**
 * 单位定义属性构建器
 * 
 * 用于构建单位定义的属性，支持链式调用
 */
class UnitAttributes implements Arrayable
{
    protected array $attributes = [];
    
    /**
     * 创建实例
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * 设置符号
     */
    public function symbol(string $symbol): static
    {
        $this->attributes['symbol'] = $symbol;
        return $this;
    }
    
    /**
     * 设置显示名称
     */
    public function displayName(string $displayName): static
    {
        $this->attributes['display_name'] = $displayName;
        return $this;
    }
    
    /**
     * 设置精度
     */
    public function precision(int $precision): static
    {
        $this->attributes['precision'] = $precision;
        return $this;
    }
    
    /**
     * 设置基准值
     */
    public function baseValue(float $baseValue): static
    {
        $this->attributes['base_value'] = $baseValue;
        return $this;
    }
    
    /**
     * 设置为基准单位
     */
    public function asBase(): static
    {
        $this->attributes['is_base'] = true;
        $this->attributes['base_value'] = 1.0;
        return $this;
    }
    
    /**
     * 设置是否激活
     */
    public function active(bool $active = true): static
    {
        $this->attributes['is_active'] = $active;
        return $this;
    }
    
    /**
     * 设置为非激活状态
     */
    public function inactive(): static
    {
        return $this->active(false);
    }
    
    /**
     * 设置格式化模板
     */
    public function formatTemplate(string $template): static
    {
        $this->attributes['format_template'] = $template;
        return $this;
    }
    
    /**
     * 设置配置信息
     */
    public function config(array $config): static
    {
        $this->attributes['config'] = $config;
        return $this;
    }
    
    /**
     * 添加单个配置项
     */
    public function addConfig(string $key, mixed $value): static
    {
        if (!isset($this->attributes['config'])) {
            $this->attributes['config'] = [];
        }
        $this->attributes['config'][$key] = $value;
        return $this;
    }
    
    /**
     * 设置自定义属性
     */
    public function set(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    
    /**
     * 批量设置属性
     */
    public function merge(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}