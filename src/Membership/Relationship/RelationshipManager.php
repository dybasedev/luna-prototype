<?php

namespace Dybasedev\LunaPrototype\Membership\Relationship;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\MembershipBinding;
use Dybasedev\LunaPrototype\Membership\Models\MembershipRelationshipIndex;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 会员关系管理器
 * 
 * 负责管理会员之间的各种关系，如邀请关系、团队关系等
 */
class RelationshipManager
{
    /**
     * 构造函数
     * 
     * @param LunaMembershipConfigure $configure 会员系统配置对象
     * @param Cache $cache 缓存接口实例
     * @param LunaHandler $handler 处理器管理器
     */
    public function __construct(
        protected LunaMembershipConfigure $configure,
        protected Cache $cache,
        protected LunaHandler $handler
    ) {
    }

    /**
     * 创建会员关系
     * 
     * @param string $relationshipType 关系类型
     * @param SessionHolder $parent 上级成员
     * @param SessionHolder $child 下级成员
     * @param array $context 上下文数据
     * @return MembershipRelationshipIndex
     * @throws LunaException
     */
    public function createRelationship(string $relationshipType, SessionHolder $parent, SessionHolder $child, array $context = []): MembershipRelationshipIndex
    {
        // 获取关系类型处理器

        $typeHandler = $this->getRelationshipTypeHandler($relationshipType);

        // 验证是否可以建立关系
        if (!$typeHandler->validateJoin($parent, $child, $context)) {
            throw LunaException::create('Invalid relationship')
                ->withDisplayMessage('无法建立关系')
                ->withData([
                    'type' => $relationshipType,
                    'parent_id' => $parent->getOperatorId(),
                    'child_id' => $child->getOperatorId(),
                ]);
        }

        // 检查是否已存在关系（如果不允许修改）
        if (!$typeHandler->allowsModification()) {
            $existing = $this->getRelationship($relationshipType, $child);
            if ($existing) {
                throw LunaException::create('Relationship already exists')
                    ->withDisplayMessage('关系已存在')
                    ->withData([
                        'type' => $relationshipType,
                        'child_id' => $child->getOperatorId(),
                    ]);
            }
        }

        // 使用事务确保数据一致性，参考 AssetsAccount 的实现
        $callback = function () use ($relationshipType, $parent, $child, $context, $typeHandler) {
            /** @var MembershipRelationshipIndex $model */
            $model = $this->configure->relationshipIndexModel;
            
            // 查找父级关系的右值
            $parentRelation = $model::query()
                ->where('relationship_type', hash_code($relationshipType))
                ->where('owner_type', $parent->getOperatorType())
                ->where('owner_id', $parent->getOperatorId())
                ->first();

            if (!$parentRelation) {
                // 如果父级不存在，创建根节点
                $parentRelation = $this->createRootRelationship($relationshipType, $parent);
            }

            // 检查最大深度限制
            $maxDepth = $typeHandler->getMaxDepth();
            if ($maxDepth > 0 && $parentRelation->depth >= $maxDepth - 1) {
                throw LunaException::create('Maximum depth exceeded')
                    ->withDisplayMessage('超出最大层级深度')
                    ->withData([
                        'type' => $relationshipType,
                        'max_depth' => $maxDepth,
                        'current_depth' => $parentRelation->depth,
                    ]);
            }

            // 获取插入位置
            $rightValue = $parentRelation->right_value;
            
            // 为新节点腾出空间
            $model::query()
                ->where('relationship_type', hash_code($relationshipType))
                ->where('left_value', '>=', $rightValue)
                ->increment('left_value', 2);
                
            $model::query()
                ->where('relationship_type', hash_code($relationshipType))
                ->where('right_value', '>=', $rightValue)
                ->increment('right_value', 2);

            // 插入新节点
            // 使用 SessionHolder 接口获取类型
            $childOwnerType = $child->getOperatorType();
            // 获取实际的模型类名（用于后续查询）
            $childModelClass = get_class($child);
            
            $relationship = new $model([
                'owner_id' => $child->getOperatorId(),
                'owner_type' => $childOwnerType,
                'relationship_type' => hash_code($relationshipType),
                'left_value' => $rightValue,
                'right_value' => $rightValue + 1,
                'depth' => $parentRelation->depth + 1,
            ]);

            $relationship->save();
            
            // 缓存owner type映射到模型类名
            $this->cache->forever("membership:owner_type:{$childOwnerType}", $childModelClass);

            // 触发加入关系链事件
            $typeHandler->onJoin($parent, $child, $context);

            // 清除缓存
            $this->clearRelationshipCache($relationshipType, $child);

            return $relationship;
        };

        // 判断是否已在事务中
        if (DB::connection()->transactionLevel()) {
            return $callback();
        } else {
            return DB::transaction($callback);
        }
    }

    /**
     * 获取关系类型处理器
     * 
     * @param string $relationshipType
     * @return RelationshipHandler
     * @throws LunaException
     */
    protected function getRelationshipTypeHandler(string $relationshipType): RelationshipHandler
    {
        try {
            // 从已注册的处理器中查找
            $handlers = $this->handler->handlers('membership-relationships');
            
            foreach ($handlers as $handlerInfo) {
                /** @var RelationshipHandler $handlerInstance */
                $handlerInstance = app($handlerInfo['handler']);
                if ($handlerInstance->getTypeKey() === $relationshipType) {
                    return $handlerInstance;
                }
            }
            
            // 如果没找到，尝试从 entity handler 获取
            $entity = $this->handler->entityHandler($relationshipType);
            
            if ($entity) {
                // 创建处理器实例
                $handler = $this->handler->createHandlerInstance($relationshipType);
                
                if (!$handler instanceof RelationshipHandler) {
                    throw LunaException::create('无效的关系类型处理器: ' . $relationshipType)
                        ->withDisplayMessage('无效的关系类型处理器')
                        ->withData(['type' => $relationshipType]);
                }
                
                return $handler;
            }
            
            throw LunaException::create('Relationship type not found')
                ->withDisplayMessage('关系类型不存在')
                ->withData([
                    'type' => $relationshipType,
                    'registered_handlers' => array_map(fn($h) => $h['handler'], $handlers)
                ]);
            
        } catch (\Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            
            throw LunaException::create('Relationship type handler error: ' . $e->getMessage())
                ->withDisplayMessage('获取关系类型处理器失败')
                ->withData([
                    'type' => $relationshipType,
                    'error' => $e->getMessage()
                ]);
        }
    }

    /**
     * 创建根节点关系
     * 
     * @param string $relationshipType
     * @param SessionHolder $owner
     * @return MembershipRelationshipIndex
     */
    protected function createRootRelationship(string $relationshipType, SessionHolder $owner): MembershipRelationshipIndex
    {
        /** @var MembershipRelationshipIndex $model */
        $model = $this->configure->relationshipIndexModel;
        
        // 获取当前最大右值
        $maxRight = $model::query()
            ->where('relationship_type', hash_code($relationshipType))
            ->max('right_value') ?? 0;

        $ownerType = $owner->getOperatorType();
        // 获取实际的模型类名（用于后续查询）
        $ownerModelClass = get_class($owner);
        
        $relationship = new $model([
            'owner_id' => $owner->getOperatorId(),
            'owner_type' => $ownerType,
            'relationship_type' => hash_code($relationshipType),
            'left_value' => $maxRight + 1,
            'right_value' => $maxRight + 2,
            'depth' => 0,
        ]);

        $relationship->save();
        
        // 缓存owner type映射到模型类名
        $this->cache->forever("membership:owner_type:{$ownerType}", $ownerModelClass);

        return $relationship;
    }

    /**
     * 获取成员的关系信息
     * 
     * @param string $relationshipType
     * @param SessionHolder $holder
     * @return MembershipRelationshipIndex|null
     */
    protected function getRelationship(string $relationshipType, SessionHolder $holder): ?MembershipRelationshipIndex
    {
        /** @var MembershipRelationshipIndex $model */
        $model = $this->configure->relationshipIndexModel;
        
        return $model::query()
            ->where('relationship_type', hash_code($relationshipType))
            ->where('owner_type', $holder->getOperatorType())
            ->where('owner_id', $holder->getOperatorId())
            ->first();
    }

    /**
     * 获取直接上级
     * 
     * @param string $relationshipType
     * @param SessionHolder $child
     * @return SessionHolder|null
     */
    public function getParent(string $relationshipType, SessionHolder $child): ?SessionHolder
    {
        $childRelation = $this->getRelationship($relationshipType, $child);
        if (!$childRelation || $childRelation->depth === 0) {
            return null;
        }

        /** @var MembershipRelationshipIndex $model */
        $model = $this->configure->relationshipIndexModel;
        
        // 查找直接上级（左值小于子节点左值，右值大于子节点右值，深度为子节点深度-1）
        $parentRelation = $model::query()
            ->where('relationship_type', hash_code($relationshipType))
            ->where('left_value', '<', $childRelation->left_value)
            ->where('right_value', '>', $childRelation->right_value)
            ->where('depth', $childRelation->depth - 1)
            ->first();

        if (!$parentRelation) {
            return null;
        }

        // 通过 owner_type 的 hash code 反查模型类名
        $ownerClass = $this->getOwnerClass($parentRelation->owner_type);
        if (!$ownerClass) {
            return null;
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model&SessionHolder> $ownerClass */
        $parentModel = $ownerClass::find($parentRelation->owner_id);
        if (!$parentModel) {
            return null;
        }
        
        // Return the model directly if it implements SessionHolder
        if ($parentModel instanceof SessionHolder) {
            return $parentModel;
        }
        
        return null;
    }

    /**
     * 获取直接下级
     * 
     * @param string $relationshipType
     * @param SessionHolder $parent
     * @return \Illuminate\Support\Collection
     */
    public function getChildren(string $relationshipType, SessionHolder $parent): \Illuminate\Support\Collection
    {
        $parentRelation = $this->getRelationship($relationshipType, $parent);
        if (!$parentRelation) {
            return collect();
        }

        /** @var MembershipRelationshipIndex $model */
        $model = $this->configure->relationshipIndexModel;
        
        // 查找直接下级（左值大于父节点左值，右值小于父节点右值，深度为父节点深度+1）
        $childRelations = $model::query()
            ->where('relationship_type', hash_code($relationshipType))
            ->where('left_value', '>', $parentRelation->left_value)
            ->where('right_value', '<', $parentRelation->right_value)
            ->where('depth', $parentRelation->depth + 1)
            ->get();

        // 批量查询所有子成员
        $children = collect();
        
        foreach ($childRelations->groupBy('owner_type') as $ownerType => $relations) {
            $ownerClass = $this->getOwnerClass($ownerType);
            if ($ownerClass) {
                $ids = $relations->pluck('owner_id');
                /** @var class-string<\Illuminate\Database\Eloquent\Model&SessionHolder> $ownerClass */
                $models = $ownerClass::whereIn('id', $ids)->get();
                
                // Add models that implement SessionHolder
                foreach ($models as $model) {
                    if ($model instanceof SessionHolder) {
                        $children->push($model);
                    }
                }
            }
        }

        return $children;
    }

    /**
     * 获取所有上级
     * 
     * @param string $relationshipType
     * @param SessionHolder $child
     * @return \Illuminate\Support\Collection
     */
    public function getAncestors(string $relationshipType, SessionHolder $child): \Illuminate\Support\Collection
    {
        $childRelation = $this->getRelationship($relationshipType, $child);
        if (!$childRelation || $childRelation->depth === 0) {
            return collect();
        }

        /** @var MembershipRelationshipIndex $model */
        $model = $this->configure->relationshipIndexModel;
        
        // 查找所有上级（左值小于子节点左值，右值大于子节点右值）
        $ancestorRelations = $model::query()
            ->where('relationship_type', hash_code($relationshipType))
            ->where('left_value', '<', $childRelation->left_value)
            ->where('right_value', '>', $childRelation->right_value)
            ->orderBy('depth')
            ->get();

        // 批量查询所有上级成员
        $ancestors = collect();
        
        foreach ($ancestorRelations->groupBy('owner_type') as $ownerType => $relations) {
            $ownerClass = $this->getOwnerClass($ownerType);
            if ($ownerClass) {
                $ids = $relations->pluck('owner_id');
                /** @var class-string<\Illuminate\Database\Eloquent\Model&SessionHolder> $ownerClass */
                $models = $ownerClass::whereIn('id', $ids)->get()->keyBy('id');
                
                // 按照深度顺序添加到结果集合
                foreach ($relations as $relation) {
                    if (isset($models[$relation->owner_id])) {
                        $model = $models[$relation->owner_id];
                        if ($model instanceof SessionHolder) {
                            $ancestors->push($model);
                        }
                    }
                }
            }
        }

        return $ancestors;
    }

    /**
     * 获取所有下级
     * 
     * @param string $relationshipType
     * @param SessionHolder $parent
     * @return \Illuminate\Support\Collection
     */
    public function getDescendants(string $relationshipType, SessionHolder $parent): \Illuminate\Support\Collection
    {
        $parentRelation = $this->getRelationship($relationshipType, $parent);
        if (!$parentRelation) {
            return collect();
        }

        /** @var MembershipRelationshipIndex $model */
        $model = $this->configure->relationshipIndexModel;
        
        // 查找所有下级（左值大于父节点左值，右值小于父节点右值）
        $descendantRelations = $model::query()
            ->where('relationship_type', hash_code($relationshipType))
            ->where('left_value', '>', $parentRelation->left_value)
            ->where('right_value', '<', $parentRelation->right_value)
            ->orderBy('depth')
            ->get();

        // 批量查询所有下级成员
        $descendants = collect();
        
        foreach ($descendantRelations->groupBy('owner_type') as $ownerType => $relations) {
            $ownerClass = $this->getOwnerClass($ownerType);
            if ($ownerClass) {
                $ids = $relations->pluck('owner_id');
                /** @var class-string<\Illuminate\Database\Eloquent\Model&SessionHolder> $ownerClass */
                $models = $ownerClass::whereIn('id', $ids)->get();
                
                // Add models that implement SessionHolder
                foreach ($models as $model) {
                    if ($model instanceof SessionHolder) {
                        $descendants->push($model);
                    }
                }
            }
        }

        return $descendants;
    }

    /**
     * 根据 owner_type 的 hash code 获取对应的模型类名或查询构建器
     * 
     * @param int $ownerType
     * @return string|null 返回模型类名或 null
     */
    protected function getOwnerClass(int $ownerType): ?string
    {
        // 从配置的绑定中查找对应的模型类
        foreach ($this->configure->bindings as $binding) {
            // 检查 owner 类的 hash 是否匹配
            if ($binding->owner && hash_code($binding->owner) === $ownerType) {
                // 如果绑定使用的是模型类，直接返回
                if ($binding->tableIsModelClass && $binding->table) {
                    return $binding->table;
                }
                // 否则返回 owner 类名（实现了 SessionHolder 的模型）
                return $binding->owner;
            }
        }

        // 如果没有找到，尝试从缓存中查找
        $cachedClass = $this->cache->get("membership:owner_type:{$ownerType}");
        if ($cachedClass) {
            return $cachedClass;
        }

        return null;
    }

    /**
     * 清除关系缓存
     * 
     * @param string $relationshipType
     * @param SessionHolder $holder
     * @return void
     */
    protected function clearRelationshipCache(string $relationshipType, SessionHolder $holder): void
    {
        $cacheKey = sprintf(
            'membership:relationship:%s:%s:%s',
            $relationshipType,
            $holder->getOperatorTypeName(),
            $holder->getOperatorId()
        );
        
        $this->cache->forget($cacheKey);
    }

    /**
     * 在事务中执行操作
     * 
     * 如果已经在事务中，直接执行回调
     * 否则开启新事务
     * 
     * @param callable $callback
     * @return mixed
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        if (DB::connection()->transactionLevel()) {
            return $callback();
        }
        
        return DB::transaction($callback);
    }

    /**
     * 根据绑定获取查询构建器
     * 
     * @param MembershipBinding $binding
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    protected function getQueryBuilderFromBinding(MembershipBinding $binding)
    {
        if ($binding->tableIsModelClass && $binding->table) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $binding->table;
            return $modelClass::query();
        }
        
        // 如果不是模型类，使用表名查询
        return DB::table($binding->tableName);
    }
}