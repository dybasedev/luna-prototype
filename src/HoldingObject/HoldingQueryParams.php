<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 持有对象查询参数构造器
 * 
 * 用于构建查询持有对象所需的参数
 * 
 * @package Dybasedev\LunaPrototype\HoldingObject
 */
class HoldingQueryParams
{
    /**
     * 所有者
     * 
     * @var SessionHolder|null
     */
    public protected(set) ?SessionHolder $owner = null;
    
    /**
     * 对象名称
     * 
     * @var string|int|null
     */
    public protected(set) string|int|null $objectName = null;
    
    /**
     * 对象ID
     * 
     * @var string|int|null
     */
    public protected(set) string|int|null $objectId = null;
    
    /**
     * 状态过滤
     * 
     * @var int|null
     */
    public protected(set) ?int $status = null;
    
    /**
     * 最小数量
     * 
     * @var float|null
     */
    public protected(set) ?float $minQuantity = null;
    
    /**
     * 最大数量
     * 
     * @var float|null
     */
    public protected(set) ?float $maxQuantity = null;
    
    /**
     * 排序字段
     * 
     * @var string
     */
    public protected(set) string $orderBy = 'created_at';
    
    /**
     * 排序方向
     * 
     * @var string
     */
    public protected(set) string $orderDirection = 'desc';
    
    /**
     * 限制数量
     * 
     * @var int|null
     */
    public protected(set) ?int $limit = null;
    
    /**
     * 偏移量
     * 
     * @var int|null
     */
    public protected(set) ?int $offset = null;
    
    /**
     * 创建查询参数构造器实例
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * 设置所有者
     */
    public function owner(SessionHolder $owner): static
    {
        $this->owner = $owner;
        return $this;
    }
    
    /**
     * 设置对象
     */
    public function object(string|int $objectName, string|int|null $objectId = null): static
    {
        $this->objectName = $objectName;
        if ($objectId !== null) {
            $this->objectId = $objectId;
        }
        return $this;
    }
    
    /**
     * 设置对象ID
     */
    public function objectId(string|int $objectId): static
    {
        $this->objectId = $objectId;
        return $this;
    }
    
    /**
     * 设置状态过滤
     */
    public function status(HoldingStatus|int $status): static
    {
        $this->status = $status instanceof HoldingStatus ? $status->value : $status;
        return $this;
    }
    
    /**
     * 只查询正常状态
     */
    public function normal(): static
    {
        return $this->status(HoldingStatus::Normal);
    }
    
    /**
     * 只查询活跃状态（正常或冻结）
     */
    public function active(): static
    {
        // 注意：这里需要在实际查询时处理多个状态
        $this->status = HoldingStatus::Normal->value;
        return $this;
    }
    
    /**
     * 设置数量范围
     */
    public function quantityRange(?float $min = null, ?float $max = null): static
    {
        $this->minQuantity = $min;
        $this->maxQuantity = $max;
        return $this;
    }
    
    /**
     * 设置最小数量
     */
    public function minQuantity(float $quantity): static
    {
        $this->minQuantity = $quantity;
        return $this;
    }
    
    /**
     * 设置最大数量
     */
    public function maxQuantity(float $quantity): static
    {
        $this->maxQuantity = $quantity;
        return $this;
    }
    
    /**
     * 设置排序
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $this->orderBy = $field;
        $this->orderDirection = strtolower($direction);
        return $this;
    }
    
    /**
     * 按创建时间倒序
     */
    public function latest(): static
    {
        return $this->orderBy('created_at', 'desc');
    }
    
    /**
     * 按创建时间正序
     */
    public function oldest(): static
    {
        return $this->orderBy('created_at', 'asc');
    }
    
    /**
     * 设置限制数量
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * 设置偏移量
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * 设置分页
     */
    public function page(int $page, int $perPage = 15): static
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }
    
    /**
     * 构建过滤条件数组
     */
    public function buildFilters(): array
    {
        $filters = [];
        
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        
        if ($this->objectName !== null) {
            $filters['object_type'] = $this->objectName;
        }
        
        if ($this->minQuantity !== null) {
            $filters['min_quantity'] = $this->minQuantity;
        }
        
        if ($this->maxQuantity !== null) {
            $filters['max_quantity'] = $this->maxQuantity;
        }
        
        return $filters;
    }
    
    /**
     * 应用到查询构建器
     */
    public function applyToQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if ($this->owner !== null) {
            $query->where('owner_id', $this->owner->getOperatorId())
                  ->where('owner_type', is_string($this->owner->getOperatorType()) 
                      ? hash_code($this->owner->getOperatorType()) 
                      : $this->owner->getOperatorType());
        }
        
        if ($this->objectName !== null) {
            $objectType = is_string($this->objectName) ? hash_code($this->objectName) : $this->objectName;
            $query->where('object_type', $objectType);
        }
        
        if ($this->objectId !== null) {
            $query->where('object_id', $this->objectId);
        }
        
        if ($this->status !== null) {
            $query->where('status', $this->status);
        }
        
        if ($this->minQuantity !== null) {
            $query->where('quantity', '>=', $this->minQuantity);
        }
        
        if ($this->maxQuantity !== null) {
            $query->where('quantity', '<=', $this->maxQuantity);
        }
        
        $query->orderBy($this->orderBy, $this->orderDirection);
        
        if ($this->limit !== null) {
            $query->limit($this->limit);
        }
        
        if ($this->offset !== null) {
            $query->offset($this->offset);
        }
        
        return $query;
    }
}