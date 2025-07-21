<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class LunaHoldingObject extends LunaModule
{
    public function __construct(
        protected(set) LunaHoldingObjectConfigure $configure,
        protected Cache $cache
    )
    {
    }

    private function getId(string|int $name): int
    {
        return is_string($name) ? hash_code($name) : $name;
    }

    public function hasUniqueObject(string|int $name): bool
    {
        return isset($this->configure->registeredUniqueObjects[$this->getId($name)]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function getUniqueObjectInstance(string|int $name): UniqueObject
    {
        $id = $this->getId($name);
        if (!$this->hasUniqueObject($id)) {
            throw LunaException::create("Unique object [$name] does not exists.");
        }

        $object = $this->configure->registeredUniqueObjects[$id];

        if ($object instanceof UniqueObject) {
            return $object;
        }

        /** @var class-string<UniqueObject> $className */
        [$name, $className] = $object;

        /** @var UniqueObject $instance */
        $instance = app()->make($className);

        return $instance->named($name);
    }

    /**
     * 创建唯一对象持有记录
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param array $payload 载荷数据
     * @param float $quantity 数量
     * @param int|null $unitId 单位ID
     * @param int|string|null $eventId 事件ID
     * @return Models\UniqueObjectHolding
     * @throws LunaException
     * @throws BindingResolutionException
     */
    public function createUniqueHolding(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        array $payload = [],
        float $quantity = 1.0,
        ?int $unitId = null,
        int|string|null $eventId = null
    ): Models\UniqueObjectHolding {
        $uniqueObject = $this->getUniqueObjectInstance($objectName);
        
        // 格式化对象ID
        $objectId = $uniqueObject->reformatId($objectId);
        
        // 检查权限
        if (!$uniqueObject->permit($owner, $objectId, $payload)) {
            throw LunaException::create('Permission denied for holding this object')
                ->withDisplayMessage('您没有权限持有该对象');
        }
        
        // 验证载荷
        if (!$uniqueObject->validatePayload($payload)) {
            throw LunaException::create('Invalid payload data')
                ->withDisplayMessage('载荷数据验证失败');
        }
        
        $objectType = $this->getId($objectName);
        $ownerId = $owner->getOperatorId();
        $ownerType = is_string($owner->getOperatorType()) ? hash_code($owner->getOperatorType()) : $owner->getOperatorType();
        
        // 如果启用缓存锁，使用原子锁确保并发安全
        if ($this->configure->useCacheLock) {
            try {
                // 构建锁的 key
                $lockKey = sprintf('holding:lock:%d:%d:%d:%d', $ownerType, $ownerId, $objectType, $objectId);
                
                // 尝试使用缓存锁
                if (method_exists($this->cache, 'lock')) {
                    return $this->cache->lock($lockKey, $this->configure->lockTimeout)->block(
                        $this->configure->lockWaitTimeout,
                        function () use (
                            $owner, $objectName, $objectId, $objectType, $ownerId, $ownerType,
                            $payload, $quantity, $unitId, $uniqueObject, $eventId
                        ) {
                            return $this->doCreateWithCheck(
                                $owner, $objectName, $objectId, $objectType, $ownerId, $ownerType,
                                $payload, $quantity, $unitId, $uniqueObject, $eventId
                            );
                        }
                    );
                }
            } catch (\BadMethodCallException $e) {
                // 缓存驱动不支持锁，继续执行
            }
        }
        
        // 不使用缓存锁，直接执行
        return $this->doCreateWithCheck(
            $owner, $objectName, $objectId, $objectType, $ownerId, $ownerType,
            $payload, $quantity, $unitId, $uniqueObject, $eventId
        );
    }
    
    /**
     * 执行创建前的检查并创建
     */
    protected function doCreateWithCheck(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        int $objectType,
        int $ownerId,
        int $ownerType,
        array $payload,
        float $quantity,
        ?int $unitId,
        UniqueObject $uniqueObject,
        int|string|null $eventId = null
    ): Models\UniqueObjectHolding {
        // 再次检查是否已存在持有记录
        $existingHolding = $this->getUniqueHolding($owner, $objectName, $objectId);
        
        // 如果已存在且不允许多次持有
        if ($existingHolding && !$uniqueObject->enableHoldMultiple) {
            throw LunaException::create('Already holding this object')
                ->withDisplayMessage($uniqueObject->conflictMessage ?: '您已持有该对象');
        }
        
        // 如果允许多次持有且已存在，则更新数量
        if ($existingHolding && $uniqueObject->enableHoldMultiple) {
            return $this->updateUniqueHoldingQuantity($existingHolding, $quantity, $payload);
        }
        
        // 创建新的持有记录
        return $this->doCreateHolding(
            $objectType, $objectId, $ownerId, $ownerType,
            $payload, $quantity, $unitId, $uniqueObject, $eventId
        );
    }
    
    /**
     * 执行创建持有记录
     *
     * @param int $objectType
     * @param string|int $objectId
     * @param int $ownerId
     * @param int $ownerType
     * @param array $payload
     * @param float $quantity
     * @param int|null $unitId
     * @param UniqueObject $uniqueObject
     * @param int|string|null $eventId
     * @return Models\UniqueObjectHolding
     */
    protected function doCreateHolding(
        int $objectType,
        string|int $objectId,
        int $ownerId,
        int $ownerType,
        array $payload,
        float $quantity,
        ?int $unitId,
        UniqueObject $uniqueObject,
        int|string|null $eventId = null
    ): Models\UniqueObjectHolding {
        // 如果启用了数据库唯一索引冲突处理，使用 upsert
        if ($this->configure->useDbUniqueConflictHandling && $uniqueObject->enableHoldMultiple) {
            $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
            return $this->upsertHolding(
                $objectType, $objectId, $ownerId, $ownerType,
                $payload, $quantity, $unitId, $uniqueObject, $realEventId
            );
        }
        
        // 否则使用常规创建
        return DB::transaction(function () use (
            $objectType, $objectId, $ownerId, $ownerType,
            $payload, $quantity, $unitId, $uniqueObject, $eventId
        ) {
            /** @var Models\UniqueObjectHolding $holding */
            $holding = $this->configure->uniqueObjectHoldingModel::create([
                'object_type' => $objectType,
                'object_id' => $objectId,
                'owner_id' => $ownerId,
                'owner_type' => $ownerType,
                'exists_extended' => false,
                'payload' => $payload,
                'status' => $this->configure->defaultStatus->value,
                'quantity' => $quantity,
                'unit_id' => $unitId,
            ]);
            
            // 触发创建事件
            $uniqueObject->createdHolding($holding);
            
            // 记录变动日志
            if ($this->configure->enableChangeLog) {
                $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
                $this->logChange($holding, 0, $quantity, 0, $this->configure->defaultStatus->value, $realEventId, [
                    'action' => 'create',
                    'payload' => $payload,
                ]);
            }
            
            // 清除缓存
            $this->clearExistenceCacheByHolding($holding);
            
            return $holding;
        });
    }
    
    /**
     * 使用 upsert 创建或更新持有记录
     *
     * @param int $objectType
     * @param string|int $objectId
     * @param int $ownerId
     * @param int $ownerType
     * @param array $payload
     * @param float $quantity
     * @param int|null $unitId
     * @param UniqueObject $uniqueObject
     * @param int $eventId
     * @return Models\UniqueObjectHolding
     */
    protected function upsertHolding(
        int $objectType,
        string|int $objectId,
        int $ownerId,
        int $ownerType,
        array $payload,
        float $quantity,
        ?int $unitId,
        UniqueObject $uniqueObject,
        int $eventId = 0
    ): Models\UniqueObjectHolding {
        return DB::transaction(function () use (
            $objectType, $objectId, $ownerId, $ownerType,
            $payload, $quantity, $unitId, $uniqueObject, $eventId
        ) {
            // 使用原生 SQL 实现 ON DUPLICATE KEY UPDATE
            $table = (new $this->configure->uniqueObjectHoldingModel)->getTable();
            
            DB::statement("
                INSERT INTO {$table} (
                    object_type, object_id, owner_id, owner_type,
                    exists_extended, payload, status, quantity, unit_id,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    payload = JSON_MERGE_PATCH(payload, VALUES(payload)),
                    updated_at = VALUES(updated_at)
            ", [
                $objectType,
                $objectId,
                $ownerId,
                $ownerType,
                false,
                json_encode($payload),
                $this->configure->defaultStatus->value,
                $quantity,
                $unitId,
                now(),
                now()
            ]);
            
            // 获取刚才创建或更新的记录
            $holding = $this->configure->uniqueObjectHoldingModel::query()
                ->where('owner_id', $ownerId)
                ->where('owner_type', $ownerType)
                ->where('object_type', $objectType)
                ->where('object_id', $objectId)
                ->first();
            
            // 触发创建事件
            $uniqueObject->createdHolding($holding);
            
            // 记录变动日志
            if ($this->configure->enableChangeLog && $holding) {
                $beforeQuantity = $holding->quantity - $quantity;
                $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
                $this->logChange($holding, $beforeQuantity, $holding->quantity, $holding->status, $holding->status, $realEventId, [
                    'action' => $beforeQuantity > 0 ? 'update_quantity' : 'create',
                    'payload' => $payload,
                ]);
            }
            
            return $holding;
        });
    }

    /**
     * 获取唯一对象持有记录
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param bool $forceNoCache 是否强制不使用缓存
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     */
    public function getUniqueHolding(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        bool $forceNoCache = false
    ): ?Models\UniqueObjectHolding {
        $uniqueObject = $this->getUniqueObjectInstance($objectName);
        $objectId = $uniqueObject->reformatId($objectId);
        
        // 如果启用缓存且不强制绕过缓存，先检查缓存中的存在性
        if (!$forceNoCache && $this->configure->enableExistenceCache) {
            $cacheKey = $this->getExistenceCacheKey($owner, $objectName, $objectId);
            $cachedExists = $this->cache->get($cacheKey);
            
            // 如果缓存显示不存在，直接返回 null
            if ($cachedExists === false) {
                return null;
            }
        }
        
        $holding = $this->configure->uniqueObjectHoldingModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', is_string($owner->getOperatorType()) ? hash_code($owner->getOperatorType()) : $owner->getOperatorType())
            ->where('object_type', $this->getId($objectName))
            ->where('object_id', $objectId)
            ->first();
            
        // 更新缓存
        if ($this->configure->enableExistenceCache) {
            $cacheKey = $this->getExistenceCacheKey($owner, $objectName, $objectId);
            $this->cache->put($cacheKey, $holding !== null, $this->configure->existenceCacheTTL);
        }
        
        return $holding;
    }

    /**
     * 检查是否持有唯一对象
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param bool $forceNoCache 是否强制不使用缓存
     * @return bool
     * @throws BindingResolutionException
     */
    public function hasUniqueHolding(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        bool $forceNoCache = false
    ): bool {
        if (!$forceNoCache && $this->configure->enableExistenceCache) {
            $cacheKey = $this->getExistenceCacheKey($owner, $objectName, $objectId);
            
            // 尝试从缓存获取
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $exists = $this->getUniqueHolding($owner, $objectName, $objectId, $forceNoCache) !== null;
        
        // 存入缓存
        if ($this->configure->enableExistenceCache) {
            $cacheKey = $this->getExistenceCacheKey($owner, $objectName, $objectId);
            $this->cache->put($cacheKey, $exists, $this->configure->existenceCacheTTL);
        }
        
        return $exists;
    }

    /**
     * 获取唯一对象持有查询构建器
     *
     * @param SessionHolder|null $owner 所有者，如果为空则查询所有
     * @param string|int|null $objectName 对象名称，如果为空则查询所有类型
     * @return Builder
     */
    public function queryUniqueHoldings(
        ?SessionHolder $owner = null,
        string|int|null $objectName = null
    ): Builder {
        $query = $this->configure->uniqueObjectHoldingModel::query();
        
        if ($owner) {
            $query->where('owner_id', $owner->getOperatorId())
                  ->where('owner_type', is_string($owner->getOperatorType()) ? hash_code($owner->getOperatorType()) : $owner->getOperatorType());
        }
        
        if ($objectName !== null) {
            $query->where('object_type', $this->getId($objectName));
        }
        
        return $query;
    }

    /**
     * 更新唯一对象持有数量
     *
     * @param Models\UniqueObjectHolding $holding 持有记录
     * @param float $quantity 新增数量
     * @param array $payload 新的载荷数据（可选）
     * @return Models\UniqueObjectHolding
     */
    protected function updateUniqueHoldingQuantity(
        Models\UniqueObjectHolding $holding,
        float $quantity,
        array $payload = []
    ): Models\UniqueObjectHolding {
        return DB::transaction(function () use ($holding, $quantity, $payload) {
            $beforeQuantity = $holding->quantity;
            $newQuantity = $beforeQuantity + $quantity;
            
            $holding->quantity = $newQuantity;
            if (!empty($payload)) {
                $holding->payload = array_merge($holding->payload, $payload);
            }
            $holding->save();
            
            // 记录变动日志
            if ($this->configure->enableChangeLog) {
                $eventId = $payload['event_id'] ?? 0;
                $realEventId = is_string($eventId) ? hash_code($eventId) : $eventId;
                $this->logChange(
                    $holding,
                    $beforeQuantity,
                    $newQuantity,
                    $holding->status,
                    $holding->status,
                    $realEventId,
                    [
                        'action' => 'update_quantity',
                        'payload' => $payload,
                    ]
                );
            }
            
            return $holding;
        });
    }

    /**
     * 记录变动日志
     *
     * @param Models\UniqueObjectHolding $holding
     * @param float $beforeQuantity
     * @param float $afterQuantity
     * @param int $beforeStatus
     * @param int $afterStatus
     * @param int $eventId
     * @param array $payload
     * @return Models\UniqueObjectHoldingChangeLog|null
     */
    protected function logChange(
        Models\UniqueObjectHolding $holding,
        float $beforeQuantity,
        float $afterQuantity,
        int $beforeStatus,
        int $afterStatus,
        int $eventId = 0,
        array $payload = []
    ): ?Models\UniqueObjectHoldingChangeLog {
        if (!$this->configure->enableChangeLog) {
            return null;
        }
        
        return $this->configure->uniqueObjectHoldingChangeLogModel::create([
            'holding_id' => $holding->id,
            'owner_id' => $holding->owner_id,
            'owner_type' => $holding->owner_type,
            'change_quantity' => $afterQuantity - $beforeQuantity,
            'before_quantity' => $beforeQuantity,
            'change_status' => $afterStatus,
            'before_status' => $beforeStatus,
            'event_id' => $eventId,
            'payload' => $payload,
            'expired_at' => $payload['expired_at'] ?? null,
        ]);
    }

    /**
     * 更新唯一对象持有状态
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param HoldingStatus $status 新状态
     * @param array $payload 额外载荷数据
     * @param int|string|null $eventId 事件ID
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     */
    public function updateUniqueHoldingStatus(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        HoldingStatus $status,
        array $payload = [],
        int|string|null $eventId = null
    ): ?Models\UniqueObjectHolding {
        $holding = $this->getUniqueHolding($owner, $objectName, $objectId, true);
        
        if (!$holding) {
            return null;
        }

        return DB::transaction(function () use ($holding, $status, $payload, $eventId, $owner, $objectName, $objectId) {
            $beforeStatus = $holding->status;
            
            $holding->status = $status->value;
            if (!empty($payload)) {
                $holding->payload = array_merge($holding->payload, $payload);
            }
            $holding->save();
            
            // 如果状态变为不活跃，清除缓存
            if (!$status->isActive()) {
                $this->clearExistenceCache($owner, $objectName, $objectId);
            }
            
            // 记录变动日志
            if ($this->configure->enableChangeLog) {
                $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
                $this->logChange(
                    $holding,
                    $holding->quantity,
                    $holding->quantity,
                    $beforeStatus,
                    $status->value,
                    $realEventId,
                    [
                        'action' => 'update_status',
                        'payload' => $payload,
                    ]
                );
            }
            
            return $holding;
        });
    }

    /**
     * 增加唯一对象持有数量
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param float $quantity 增加数量
     * @param array $payload 额外载荷数据
     * @param int|string|null $eventId 事件ID
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     * @throws LunaException
     */
    public function increaseUniqueHoldingQuantity(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        float $quantity,
        array $payload = [],
        int|string|null $eventId = null
    ): ?Models\UniqueObjectHolding {
        if ($quantity <= 0) {
            throw LunaException::create('Increase quantity must be positive')
                ->withDisplayMessage('增加数量必须为正数');
        }

        $uniqueObject = $this->getUniqueObjectInstance($objectName);
        
        // 检查单次增加限制
        if ($uniqueObject->maxIncreaseQuantity !== null && $quantity > $uniqueObject->maxIncreaseQuantity) {
            throw LunaException::create('Increase quantity exceeded')
                ->withDisplayMessage($uniqueObject->getIncreaseExceededMessage($quantity, $payload));
        }

        $holding = $this->getUniqueHolding($owner, $objectName, $objectId);
        
        if (!$holding) {
            // 如果不存在，创建新的持有记录
            return $this->createUniqueHolding($owner, $objectName, $objectId, $payload, $quantity, null, $eventId);
        }

        // 检查状态是否允许操作
        $status = HoldingStatus::from($holding->status);
        if (!$status->isActive()) {
            throw LunaException::create('Holding status does not allow increase')
                ->withDisplayMessage('当前状态不允许增加数量');
        }

        // 使用数据库行锁进行原子操作
        return DB::transaction(function () use ($holding, $quantity, $payload, $eventId, $uniqueObject) {
            $beforeQuantity = $holding->quantity;
            
            // 检查最大数量限制
            if ($uniqueObject->maxQuantity !== null) {
                $newQuantity = $beforeQuantity + $quantity;
                if ($newQuantity > $uniqueObject->maxQuantity) {
                    throw LunaException::create('Quantity limit exceeded')
                        ->withDisplayMessage($uniqueObject->getQuantityExceededMessage($beforeQuantity, $quantity, $payload));
                }
            }

            // 使用原子更新操作
            $affected = Models\UniqueObjectHolding::where('id', $holding->id)
                ->where('status', HoldingStatus::Normal->value)
                ->update([
                    'quantity' => DB::raw("quantity + {$quantity}"),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw LunaException::create('Failed to increase quantity')
                    ->withDisplayMessage('增加数量失败，请重试');
            }

            // 重新加载模型
            $holding->refresh();

            // 合并载荷数据
            if (!empty($payload)) {
                $holding->payload = array_merge($holding->payload, $payload);
                $holding->save();
            }

            // 记录变动日志
            if ($this->configure->enableChangeLog) {
                $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
                $this->logChange(
                    $holding,
                    $beforeQuantity,
                    $holding->quantity,
                    $holding->status,
                    $holding->status,
                    $realEventId,
                    [
                        'action' => 'increase_quantity',
                        'change_quantity' => $quantity,
                        'payload' => $payload,
                    ]
                );
            }

            return $holding;
        });
    }

    /**
     * 减少唯一对象持有数量
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param float $quantity 减少数量
     * @param array $payload 额外载荷数据
     * @param int|string|null $eventId 事件ID
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     * @throws LunaException
     */
    public function decreaseUniqueHoldingQuantity(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        float $quantity,
        array $payload = [],
        int|string|null $eventId = null
    ): ?Models\UniqueObjectHolding {
        if ($quantity <= 0) {
            throw LunaException::create('Decrease quantity must be positive')
                ->withDisplayMessage('减少数量必须为正数');
        }

        $uniqueObject = $this->getUniqueObjectInstance($objectName);
        
        // 检查单次减少限制
        if ($uniqueObject->maxDecreaseQuantity !== null && $quantity > $uniqueObject->maxDecreaseQuantity) {
            throw LunaException::create('Decrease quantity exceeded')
                ->withDisplayMessage($uniqueObject->getDecreaseExceededMessage($quantity, $payload));
        }

        $holding = $this->getUniqueHolding($owner, $objectName, $objectId);
        
        if (!$holding) {
            return null;
        }

        // 检查状态是否允许操作
        $status = HoldingStatus::from($holding->status);
        if (!$status->isActive()) {
            throw LunaException::create('Holding status does not allow decrease')
                ->withDisplayMessage('当前状态不允许减少数量');
        }

        // 使用数据库行锁进行原子操作
        return DB::transaction(function () use ($holding, $quantity, $payload, $eventId, $uniqueObject) {
            $beforeQuantity = $holding->quantity;
            
            // 检查数量是否足够
            if ($beforeQuantity < $quantity) {
                throw LunaException::create('Insufficient quantity')
                    ->withDisplayMessage($uniqueObject->getInsufficientQuantityMessage($beforeQuantity, $quantity, $payload));
            }

            // 使用原子更新操作，确保不会变成负数
            $affected = Models\UniqueObjectHolding::where('id', $holding->id)
                ->where('status', HoldingStatus::Normal->value)
                ->where('quantity', '>=', $quantity)
                ->update([
                    'quantity' => DB::raw("quantity - {$quantity}"),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                // 重新检查状态，可能是并发导致的
                $holding->refresh();
                if ($holding->quantity < $quantity) {
                    throw LunaException::create('Insufficient quantity')
                        ->withDisplayMessage($uniqueObject->getInsufficientQuantityMessage($holding->quantity, $quantity, $payload));
                }
                throw LunaException::create('Failed to decrease quantity')
                    ->withDisplayMessage('减少数量失败，请重试');
            }

            // 重新加载模型
            $holding->refresh();

            // 合并载荷数据
            if (!empty($payload)) {
                $holding->payload = array_merge($holding->payload, $payload);
                $holding->save();
            }

            // 记录变动日志
            if ($this->configure->enableChangeLog) {
                $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
                $this->logChange(
                    $holding,
                    $beforeQuantity,
                    $holding->quantity,
                    $holding->status,
                    $holding->status,
                    $realEventId,
                    [
                        'action' => 'decrease_quantity',
                        'change_quantity' => -$quantity,
                        'payload' => $payload,
                    ]
                );
            }

            return $holding;
        });
    }

    /**
     * 删除唯一对象持有记录
     *
     * @param SessionHolder $owner 所有者
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param array $reason 删除原因
     * @param int|string|null $eventId 事件ID
     * @return bool
     * @throws BindingResolutionException
     */
    public function deleteUniqueHolding(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId,
        array $reason = [],
        int|string|null $eventId = null
    ): bool {
        $holding = $this->getUniqueHolding($owner, $objectName, $objectId);
        
        if (!$holding) {
            return false;
        }
        
        return DB::transaction(function () use ($holding, $reason, $owner, $objectName, $objectId, $eventId) {
            // 记录删除日志
            if ($this->configure->enableChangeLog) {
                $realEventId = is_string($eventId) ? hash_code($eventId) : ($eventId ?? 0);
                $this->logChange(
                    $holding,
                    $holding->quantity,
                    0,
                    $holding->status,
                    HoldingStatus::Cancelled->value,
                    $realEventId,
                    [
                        'action' => 'delete',
                        'reason' => $reason,
                    ]
                );
            }
            
            // 清除缓存
            $this->clearExistenceCache($owner, $objectName, $objectId);
            
            return $holding->delete();
        });
    }

    /**
     * 批量获取用户的所有持有记录
     *
     * @param SessionHolder $owner 所有者
     * @param array $filters 过滤条件
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOwnerHoldings(SessionHolder $owner, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->queryUniqueHoldings($owner);
        
        // 应用过滤条件
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['object_type'])) {
            $query->where('object_type', $this->getId($filters['object_type']));
        }
        
        if (isset($filters['min_quantity'])) {
            $query->where('quantity', '>=', $filters['min_quantity']);
        }
        
        return $query->get();
    }

    /**
     * 获取持有对象的所有持有者
     *
     * @param string|int $objectName 对象名称
     * @param string|int $objectId 对象ID
     * @param array $filters 过滤条件
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws BindingResolutionException
     */
    public function getObjectHolders(
        string|int $objectName,
        string|int $objectId,
        array $filters = []
    ): \Illuminate\Database\Eloquent\Collection {
        $uniqueObject = $this->getUniqueObjectInstance($objectName);
        $objectId = $uniqueObject->reformatId($objectId);
        
        $query = $this->configure->uniqueObjectHoldingModel::query()
            ->where('object_type', $this->getId($objectName))
            ->where('object_id', $objectId);
        
        // 应用过滤条件
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['min_quantity'])) {
            $query->where('quantity', '>=', $filters['min_quantity']);
        }
        
        return $query->get();
    }

    /**
     * 获取存在性缓存键
     *
     * @param SessionHolder $owner
     * @param string|int $objectName
     * @param string|int $objectId
     * @return string
     * @throws BindingResolutionException
     */
    protected function getExistenceCacheKey(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId
    ): string {
        $uniqueObject = $this->getUniqueObjectInstance($objectName);
        $objectId = $uniqueObject->reformatId($objectId);
        $objectType = $this->getId($objectName);
        $ownerId = $owner->getOperatorId();
        $ownerType = is_string($owner->getOperatorType()) ? hash_code($owner->getOperatorType()) : $owner->getOperatorType();
        
        return sprintf(
            'holding:exists:%d:%d:%d:%s',
            $ownerType,
            $ownerId,
            $objectType,
            $objectId
        );
    }

    /**
     * 清除存在性缓存
     *
     * @param SessionHolder $owner
     * @param string|int $objectName
     * @param string|int $objectId
     * @return void
     * @throws BindingResolutionException
     */
    public function clearExistenceCache(
        SessionHolder $owner,
        string|int $objectName,
        string|int $objectId
    ): void {
        if ($this->configure->enableExistenceCache) {
            $cacheKey = $this->getExistenceCacheKey($owner, $objectName, $objectId);
            $this->cache->forget($cacheKey);
        }
    }

    /**
     * 批量清除存在性缓存
     *
     * @param SessionHolder $owner
     * @param string|int|null $objectName 如果为空则清除该用户所有对象的缓存
     * @return void
     */
    public function clearExistenceCacheBatch(
        SessionHolder $owner,
        string|int|null $objectName = null
    ): void {
        if (!$this->configure->enableExistenceCache) {
            return;
        }
        
        // 如果缓存驱动支持按前缀清除
        if (method_exists($this->cache, 'deletePattern')) {
            $ownerId = $owner->getOperatorId();
            $ownerType = is_string($owner->getOperatorType()) ? hash_code($owner->getOperatorType()) : $owner->getOperatorType();
            
            if ($objectName === null) {
                // 清除该用户的所有对象缓存
                $pattern = sprintf('holding:exists:%d:%d:*', $ownerType, $ownerId);
            } else {
                // 清除该用户特定对象类型的缓存
                $objectType = $this->getId($objectName);
                $pattern = sprintf('holding:exists:%d:%d:%d:*', $ownerType, $ownerId, $objectType);
            }
            
            $this->cache->deletePattern($pattern);
        }
    }

    /**
     * 通过持有记录清除存在性缓存
     *
     * @param Models\UniqueObjectHolding $holding
     * @return void
     */
    protected function clearExistenceCacheByHolding(Models\UniqueObjectHolding $holding): void
    {
        if (!$this->configure->enableExistenceCache) {
            return;
        }
        
        // 构建缓存键
        $cacheKey = sprintf(
            'holding:exists:%d:%d:%d:%s',
            $holding->owner_type,
            $holding->owner_id,
            $holding->object_type,
            $holding->object_id
        );
        
        $this->cache->forget($cacheKey);
    }

    /**
     * 使用参数构造器创建持有记录
     *
     * @param UniqueHoldingParams $params
     * @return Models\UniqueObjectHolding
     * @throws LunaException
     * @throws BindingResolutionException
     */
    public function createWithParams(UniqueHoldingParams $params): Models\UniqueObjectHolding
    {
        return $this->createUniqueHolding(...$params->buildForCreate());
    }

    /**
     * 使用参数构造器获取持有记录
     *
     * @param UniqueHoldingParams $params
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     */
    public function getWithParams(UniqueHoldingParams $params): ?Models\UniqueObjectHolding
    {
        return $this->getUniqueHolding(...$params->buildForGet());
    }

    /**
     * 使用参数构造器检查是否持有
     *
     * @param UniqueHoldingParams $params
     * @return bool
     * @throws BindingResolutionException
     */
    public function existsWithParams(UniqueHoldingParams $params): bool
    {
        return $this->hasUniqueHolding(...$params->buildForExists());
    }

    /**
     * 使用参数构造器增加数量
     *
     * @param UniqueHoldingParams $params
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     * @throws LunaException
     */
    public function increaseWithParams(UniqueHoldingParams $params): ?Models\UniqueObjectHolding
    {
        return $this->increaseUniqueHoldingQuantity(...$params->buildForQuantityChange());
    }

    /**
     * 使用参数构造器减少数量
     *
     * @param UniqueHoldingParams $params
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     * @throws LunaException
     */
    public function decreaseWithParams(UniqueHoldingParams $params): ?Models\UniqueObjectHolding
    {
        return $this->decreaseUniqueHoldingQuantity(...$params->buildForQuantityChange());
    }

    /**
     * 使用参数构造器更新状态
     *
     * @param UniqueHoldingParams $params
     * @return Models\UniqueObjectHolding|null
     * @throws BindingResolutionException
     */
    public function updateStatusWithParams(UniqueHoldingParams $params): ?Models\UniqueObjectHolding
    {
        return $this->updateUniqueHoldingStatus(...$params->buildForStatusUpdate());
    }

    /**
     * 使用参数构造器删除持有记录
     *
     * @param UniqueHoldingParams $params
     * @return bool
     * @throws BindingResolutionException
     */
    public function deleteWithParams(UniqueHoldingParams $params): bool
    {
        return $this->deleteUniqueHolding(...$params->buildForDelete());
    }

    /**
     * 使用查询参数构造器查询
     *
     * @param HoldingQueryParams $params
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function queryWithParams(HoldingQueryParams $params): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->configure->uniqueObjectHoldingModel::query();
        return $params->applyToQuery($query);
    }

    /**
     * 使用查询参数构造器获取所有者的持有记录
     *
     * @param HoldingQueryParams $params
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOwnerHoldingsWithParams(HoldingQueryParams $params): \Illuminate\Database\Eloquent\Collection
    {
        return $this->queryWithParams($params)->get();
    }

    /**
     * 使用查询参数构造器获取对象的持有者
     *
     * @param HoldingQueryParams $params
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getObjectHoldersWithParams(HoldingQueryParams $params): \Illuminate\Database\Eloquent\Collection
    {
        return $this->queryWithParams($params)->get();
    }

    /**
     * 使用查询参数构造器统计数量
     *
     * @param HoldingQueryParams $params
     * @return int
     */
    public function countWithParams(HoldingQueryParams $params): int
    {
        return $this->queryWithParams($params)->count();
    }

    /**
     * 使用查询参数构造器获取第一条记录
     *
     * @param HoldingQueryParams $params
     * @return Models\UniqueObjectHolding|null
     */
    public function firstWithParams(HoldingQueryParams $params): ?Models\UniqueObjectHolding
    {
        return $this->queryWithParams($params)->first();
    }
}