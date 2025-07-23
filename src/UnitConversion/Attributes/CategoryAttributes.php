<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Attributes;

use Illuminate\Contracts\Support\Arrayable;

/**
 * 单位类别属性构建器
 * 
 * 用于构建单位类别的属性，支持链式调用
 */
class CategoryAttributes implements Arrayable
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
     * 设置显示名称
     */
    public function displayName(string $displayName): static
    {
        $this->attributes['display_name'] = $displayName;
        return $this;
    }
    
    /**
     * 设置描述
     */
    public function description(string $description): static
    {
        $this->attributes['description'] = $description;
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