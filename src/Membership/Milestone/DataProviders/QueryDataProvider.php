<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\DataProviders;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * 基于数据库查询的数据提供者
 * 
 * 提供从数据库查询数据的通用实现
 */
class QueryDataProvider implements DataProvider
{
    /**
     * @param string $name 数据提供者名称
     * @param string $table 表名或模型类名
     * @param string $aggregateFunction 聚合函数（sum, count, avg, max, min）
     * @param string $aggregateColumn 聚合列名
     * @param array $conditions 查询条件
     * @param string $ownerColumn 所有者ID列名
     * @param string $ownerTypeColumn 所有者类型列名（可选）
     */
    public function __construct(
        protected string $name,
        protected string $table,
        protected string $aggregateFunction,
        protected string $aggregateColumn,
        protected array $conditions = [],
        protected string $ownerColumn = 'owner_id',
        protected ?string $ownerTypeColumn = 'owner_type'
    ) {
    }

    /**
     * 获取数据
     *
     * @param SessionHolder $owner 数据所有者
     * @param array $params 额外参数
     * @return mixed
     */
    public function getData(SessionHolder $owner, array $params = []): mixed
    {
        $query = $this->buildQuery();
        
        // 添加所有者条件
        $query->where($this->ownerColumn, $owner->getOperatorId());
        if ($this->ownerTypeColumn) {
            $query->where($this->ownerTypeColumn, $owner->getOperatorType());
        }

        // 应用额外条件
        $this->applyConditions($query, array_merge($this->conditions, $params));

        // 执行聚合查询
        return $this->executeAggregate($query);
    }

    /**
     * 获取数据提供者的名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 批量获取多个所有者的数据
     *
     * @param array<SessionHolder> $owners 所有者数组
     * @param array $params 额外参数
     * @return array<int, mixed>
     */
    public function getBatchData(array $owners, array $params = []): array
    {
        $query = $this->buildQuery();
        
        // 收集所有者信息
        $ownerIds = [];
        $ownerTypes = [];
        foreach ($owners as $owner) {
            $ownerIds[] = $owner->getOperatorId();
            $ownerTypes[$owner->getOperatorType()] = true;
        }

        // 添加所有者条件
        $query->whereIn($this->ownerColumn, $ownerIds);
        if ($this->ownerTypeColumn && count($ownerTypes) === 1) {
            $query->where($this->ownerTypeColumn, array_key_first($ownerTypes));
        }

        // 应用额外条件
        $this->applyConditions($query, array_merge($this->conditions, $params));

        // 分组查询
        $query->groupBy($this->ownerColumn);
        if ($this->ownerTypeColumn && count($ownerTypes) > 1) {
            $query->groupBy($this->ownerTypeColumn);
        }

        // 执行查询
        $results = $query->select(
            $this->ownerColumn,
            DB::raw("{$this->aggregateFunction}({$this->aggregateColumn}) as value")
        )->pluck('value', $this->ownerColumn)->toArray();

        // 确保所有所有者都有结果（默认为0）
        $finalResults = [];
        foreach ($owners as $owner) {
            $value = $results[$owner->getOperatorId()] ?? 0;
            // 对于数值聚合函数，确保返回数值类型
            if ($this->aggregateFunction !== 'count' && is_string($value)) {
                $value = (float) $value;
            }
            $finalResults[$owner->getOperatorId()] = $value;
        }

        return $finalResults;
    }

    /**
     * 构建查询
     *
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function buildQuery()
    {
        if (class_exists($this->table)) {
            return $this->table::query();
        }
        return DB::table($this->table);
    }

    /**
     * 应用查询条件
     *
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @param array $conditions
     * @return void
     */
    protected function applyConditions($query, array $conditions): void
    {
        foreach ($conditions as $key => $value) {
            if (is_array($value) && count($value) === 2) {
                // ['column', 'operator', 'value'] 格式
                $query->where($key, $value[0], $value[1]);
            } elseif ($value instanceof \Closure) {
                // 闭包条件
                $query->where($value);
            } else {
                // 简单相等条件
                $query->where($key, $value);
            }
        }
    }

    /**
     * 执行聚合查询
     *
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @return mixed
     */
    protected function executeAggregate($query): mixed
    {
        $result = match ($this->aggregateFunction) {
            'sum' => $query->sum($this->aggregateColumn) ?? 0,
            'count' => $query->count($this->aggregateColumn),
            'avg' => $query->avg($this->aggregateColumn) ?? 0,
            'max' => $query->max($this->aggregateColumn) ?? 0,
            'min' => $query->min($this->aggregateColumn) ?? 0,
            default => throw new \InvalidArgumentException("Unsupported aggregate function: {$this->aggregateFunction}")
        };
        
        // 对于数值聚合函数，确保返回数值类型
        if ($this->aggregateFunction !== 'count' && is_string($result)) {
            return (float) $result;
        }
        
        return $result;
    }
}