<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\LunaTrade;
use Dybasedev\LunaPrototype\Trade\TradeQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 标准交易查询构建器
 * 
 * 为标准交易流程提供链式调用接口来构建交易查询
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardTradeQueryBuilder extends TradeQueryBuilder
{
    
    
    /**
     * 按状态过滤
     * 
     * @param int $status
     * @return $this
     */
    public function whereStatus(int $status): static
    {
        $this->filters['status'] = $status;
        return $this;
    }
    
    /**
     * 按处理器过滤
     * 
     * @param string|int $handlerId
     * @return $this
     */
    public function whereHandler(string|int $handlerId): static
    {
        $this->filters['handler_id'] = is_string($handlerId) ? hash_code($handlerId) : $handlerId;
        return $this;
    }
    
    /**
     * 只查询已完成的交易
     * 
     * @return $this
     */
    public function onlyCompleted(): static
    {
        $this->filters['is_completed'] = true;
        return $this;
    }
    
    /**
     * 只查询未完成的交易
     * 
     * @return $this
     */
    public function onlyPending(): static
    {
        $this->filters['is_completed'] = false;
        return $this;
    }
    
    /**
     * 设置日期范围
     * 
     * @param \DateTimeInterface|string $from
     * @param \DateTimeInterface|string|null $to
     * @return $this
     */
    public function whereDateBetween(
        \DateTimeInterface|string $from,
        \DateTimeInterface|string|null $to = null
    ): static {
        $this->filters['date_from'] = $from;
        if ($to !== null) {
            $this->filters['date_to'] = $to;
        }
        return $this;
    }
    
    
    /**
     * 执行分页查询
     * 
     * @return LengthAwarePaginator|null
     */
    public function paginate(): ?LengthAwarePaginator
    {
        if (!$this->owner || !$this->trade) {
            return null;
        }
        
        return $this->trade->getOwnerTransactions(
            $this->owner,
            $this->filters,
            $this->perPage
        );
    }
    
    /**
     * 获取第一条记录
     * 
     * @return \Dybasedev\LunaPrototype\Trade\Models\TradeTransaction|null
     */
    public function first(): ?\Dybasedev\LunaPrototype\Trade\Models\TradeTransaction
    {
        $this->perPage(1);
        $result = $this->paginate();
        
        return $result?->items()[0] ?? null;
    }
    
    /**
     * 执行查询并返回结果
     * 
     * @return Collection|null
     */
    public function get(): ?Collection
    {
        $paginator = $this->paginate();
        return $paginator ? collect($paginator->items()) : null;
    }
}