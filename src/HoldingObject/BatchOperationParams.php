<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 批量操作参数构造器
 * 
 * 用于构建批量操作持有对象所需的参数
 * 
 * @package Dybasedev\LunaPrototype\HoldingObject
 */
class BatchOperationParams
{
    /**
     * 操作类型
     * 
     * @var string
     */
    public protected(set) string $operation = 'create';
    
    /**
     * 操作项列表
     * 
     * @var array<array{owner: SessionHolder, objectName: string|int, objectId: string|int, payload?: array, quantity?: float, unitId?: int|null, eventId?: int|string|null, status?: HoldingStatus}>
     */
    public protected(set) array $items = [];
    
    /**
     * 默认载荷数据
     * 
     * @var array
     */
    public protected(set) array $defaultPayload = [];
    
    /**
     * 默认事件ID
     * 
     * @var int|string|null
     */
    public protected(set) int|string|null $defaultEventId = null;
    
    /**
     * 是否在错误时继续
     * 
     * @var bool
     */
    public protected(set) bool $continueOnError = true;
    
    /**
     * 是否使用事务
     * 
     * @var bool
     */
    public protected(set) bool $useTransaction = false;
    
    /**
     * 创建批量操作参数构造器实例
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * 设置为创建操作
     */
    public function forCreate(): static
    {
        $this->operation = 'create';
        return $this;
    }
    
    /**
     * 设置为更新状态操作
     */
    public function forUpdateStatus(HoldingStatus $status): static
    {
        $this->operation = 'updateStatus';
        foreach ($this->items as &$item) {
            $item['status'] = $status;
        }
        return $this;
    }
    
    /**
     * 设置为删除操作
     */
    public function forDelete(): static
    {
        $this->operation = 'delete';
        return $this;
    }
    
    /**
     * 设置为增加数量操作
     */
    public function forIncrease(): static
    {
        $this->operation = 'increase';
        return $this;
    }
    
    /**
     * 设置为减少数量操作
     */
    public function forDecrease(): static
    {
        $this->operation = 'decrease';
        return $this;
    }
    
    /**
     * 添加操作项
     */
    public function addItem(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        ?array $payload = null,
        ?float $quantity = null,
        ?int $unitId = null,
        int|string|null $eventId = null
    ): static {
        $item = [
            'owner' => $owner,
            'objectName' => $objectName,
            'objectId' => $objectId,
        ];
        
        if ($payload !== null) {
            $item['payload'] = $payload;
        }
        
        if ($quantity !== null) {
            $item['quantity'] = $quantity;
        }
        
        if ($unitId !== null) {
            $item['unitId'] = $unitId;
        }
        
        if ($eventId !== null) {
            $item['eventId'] = $eventId;
        }
        
        $this->items[] = $item;
        
        return $this;
    }
    
    /**
     * 批量添加同一所有者的操作项
     */
    public function addItemsForOwner(
        SessionHolder $owner,
        array $objects,
        ?array $payload = null,
        ?float $quantity = null
    ): static {
        foreach ($objects as $object) {
            if (is_array($object)) {
                $this->addItem(
                    $owner,
                    $object['name'] ?? $object[0],
                    $object['id'] ?? $object[1],
                    $payload ?? $object['payload'] ?? null,
                    $quantity ?? $object['quantity'] ?? null,
                    $object['unitId'] ?? null,
                    $object['eventId'] ?? null
                );
            } else {
                // 假设是 objectName
                $this->addItem($owner, $object, 1, $payload, $quantity);
            }
        }
        
        return $this;
    }
    
    /**
     * 设置默认载荷数据
     */
    public function defaultPayload(array $payload): static
    {
        $this->defaultPayload = $payload;
        return $this;
    }
    
    /**
     * 设置默认事件ID
     */
    public function defaultEvent(int|string $eventId): static
    {
        $this->defaultEventId = $eventId;
        return $this;
    }
    
    /**
     * 设置是否在错误时继续
     */
    public function continueOnError(bool $continue = true): static
    {
        $this->continueOnError = $continue;
        return $this;
    }
    
    /**
     * 设置是否使用事务
     */
    public function useTransaction(bool $use = true): static
    {
        $this->useTransaction = $use;
        return $this;
    }
    
    /**
     * 获取处理后的操作项
     */
    public function getProcessedItems(): array
    {
        $processed = [];
        
        foreach ($this->items as $item) {
            // 合并默认载荷
            if (!empty($this->defaultPayload)) {
                $item['payload'] = array_merge($this->defaultPayload, $item['payload'] ?? []);
            }
            
            // 设置默认事件ID
            if ($this->defaultEventId !== null && !isset($item['eventId'])) {
                $item['eventId'] = $this->defaultEventId;
            }
            
            // 设置默认数量
            if (!isset($item['quantity'])) {
                $item['quantity'] = 1.0;
            }
            
            $processed[] = $item;
        }
        
        return $processed;
    }
    
    /**
     * 执行批量操作
     * 
     * @return array 返回操作结果
     */
    public function execute(): array
    {
        $holdingObject = luna_holding_object();
        $results = [];
        
        $executeOperation = function () use ($holdingObject, &$results) {
            foreach ($this->getProcessedItems() as $index => $item) {
                try {
                    $result = match ($this->operation) {
                        'create' => $holdingObject->createUniqueHolding(
                            $item['owner'],
                            $item['objectName'],
                            $item['objectId'],
                            $item['payload'] ?? [],
                            $item['quantity'] ?? 1.0,
                            $item['unitId'] ?? null,
                            $item['eventId'] ?? null
                        ),
                        'increase' => $holdingObject->increaseUniqueHoldingQuantity(
                            $item['owner'],
                            $item['objectName'],
                            $item['objectId'],
                            $item['quantity'] ?? 1.0,
                            $item['payload'] ?? [],
                            $item['eventId'] ?? null
                        ),
                        'decrease' => $holdingObject->decreaseUniqueHoldingQuantity(
                            $item['owner'],
                            $item['objectName'],
                            $item['objectId'],
                            $item['quantity'] ?? 1.0,
                            $item['payload'] ?? [],
                            $item['eventId'] ?? null
                        ),
                        'updateStatus' => $holdingObject->updateUniqueHoldingStatus(
                            $item['owner'],
                            $item['objectName'],
                            $item['objectId'],
                            $item['status'],
                            $item['payload'] ?? [],
                            $item['eventId'] ?? null
                        ),
                        'delete' => $holdingObject->deleteUniqueHolding(
                            $item['owner'],
                            $item['objectName'],
                            $item['objectId'],
                            $item['payload'] ?? [],
                            $item['eventId'] ?? null
                        ),
                        default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}")
                    };
                    
                    $results[$index] = [
                        'success' => true,
                        'data' => $result,
                    ];
                } catch (\Exception $e) {
                    $results[$index] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                    
                    if (!$this->continueOnError) {
                        throw $e;
                    }
                }
            }
        };
        
        if ($this->useTransaction) {
            \Illuminate\Support\Facades\DB::transaction($executeOperation);
        } else {
            $executeOperation();
        }
        
        return $results;
    }
}