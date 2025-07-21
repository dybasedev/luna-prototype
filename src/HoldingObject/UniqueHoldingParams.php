<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 唯一对象持有参数构造器
 * 
 * 用于构建操作唯一对象所需的参数
 * 
 * @package Dybasedev\LunaPrototype\HoldingObject
 */
class UniqueHoldingParams
{
    /**
     * 持有者
     * 
     * @var SessionHolder
     */
    public protected(set) SessionHolder $owner;
    
    /**
     * 对象名称
     * 
     * @var string|int
     */
    public protected(set) string|int $objectName;
    
    /**
     * 对象ID
     * 
     * @var string|int
     */
    public protected(set) string|int $objectId;
    
    /**
     * 载荷数据
     * 
     * @var array
     */
    public protected(set) array $payload = [];
    
    /**
     * 数量
     * 
     * @var float
     */
    public protected(set) float $quantity = 1.0;
    
    /**
     * 单位ID
     * 
     * @var int|null
     */
    public protected(set) ?int $unitId = null;
    
    /**
     * 事件ID
     * 
     * @var int|string|null
     */
    public protected(set) int|string|null $eventId = null;
    
    /**
     * 状态
     * 
     * @var HoldingStatus|null
     */
    public protected(set) ?HoldingStatus $status = null;
    
    /**
     * 是否强制不使用缓存
     * 
     * @var bool
     */
    public protected(set) bool $forceNoCache = false;
    
    /**
     * 创建参数构造器实例
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
     * 设置唯一对象
     */
    public function object(string|int $objectName, string|int $objectId): static
    {
        $this->objectName = $objectName;
        $this->objectId = $objectId;
        return $this;
    }
    
    /**
     * 设置载荷数据
     */
    public function payload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }
    
    /**
     * 添加载荷数据
     */
    public function with(string $key, mixed $value): static
    {
        $this->payload[$key] = $value;
        return $this;
    }
    
    /**
     * 设置数量
     */
    public function quantity(float $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }
    
    /**
     * 设置单位
     */
    public function unit(int $unitId): static
    {
        $this->unitId = $unitId;
        return $this;
    }
    
    /**
     * 设置事件
     */
    public function event(int|string $eventId): static
    {
        $this->eventId = $eventId;
        return $this;
    }
    
    /**
     * 设置状态
     */
    public function status(HoldingStatus $status): static
    {
        $this->status = $status;
        return $this;
    }
    
    /**
     * 设置强制不使用缓存
     */
    public function forceNoCache(bool $force = true): static
    {
        $this->forceNoCache = $force;
        return $this;
    }
    
    /**
     * 构建创建持有记录的参数数组
     */
    public function buildForCreate(): array
    {
        return [
            $this->owner,
            $this->objectName,
            $this->objectId,
            $this->payload,
            $this->quantity,
            $this->unitId,
            $this->eventId,
        ];
    }
    
    /**
     * 构建获取持有记录的参数数组
     */
    public function buildForGet(): array
    {
        return [
            $this->owner,
            $this->objectName,
            $this->objectId,
            $this->forceNoCache,
        ];
    }
    
    /**
     * 构建检查存在性的参数数组
     */
    public function buildForExists(): array
    {
        return [
            $this->owner,
            $this->objectName,
            $this->objectId,
            $this->forceNoCache,
        ];
    }
    
    /**
     * 构建增加/减少数量的参数数组
     */
    public function buildForQuantityChange(): array
    {
        return [
            $this->owner,
            $this->objectName,
            $this->objectId,
            $this->quantity,
            $this->payload,
            $this->eventId,
        ];
    }
    
    /**
     * 构建更新状态的参数数组
     */
    public function buildForStatusUpdate(): array
    {
        if ($this->status === null) {
            throw new \InvalidArgumentException('Status is required for status update');
        }
        
        return [
            $this->owner,
            $this->objectName,
            $this->objectId,
            $this->status,
            $this->payload,
            $this->eventId,
        ];
    }
    
    /**
     * 构建删除的参数数组
     */
    public function buildForDelete(): array
    {
        return [
            $this->owner,
            $this->objectName,
            $this->objectId,
            $this->payload, // 作为删除原因
            $this->eventId,
        ];
    }
    
}