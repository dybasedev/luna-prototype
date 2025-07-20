<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 交易查询构建器基类
 * 
 * 提供交易查询构建器的基础功能和接口定义
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class TradeQueryBuilder
{
    protected ?LunaTrade $trade = null;
    protected ?SessionHolder $owner = null;
    protected array $filters = [];
    protected int $perPage = 20;
    
    public function __construct()
    {
        $this->trade = luna_trade();
    }
    
    /**
     * 设置查询的所有者
     * 
     * @param SessionHolder $owner
     * @return $this
     */
    public function forOwner(SessionHolder $owner): static
    {
        $this->owner = $owner;
        return $this;
    }
    
    /**
     * 设置每页数量
     * 
     * @param int $perPage
     * @return $this
     */
    public function perPage(int $perPage): static
    {
        $this->perPage = $perPage;
        return $this;
    }
    
    /**
     * 执行查询并返回结果
     * 
     * @return mixed
     */
    abstract public function get(): mixed;
    
    /**
     * 执行分页查询
     * 
     * @return mixed
     */
    abstract public function paginate(): mixed;
    
    /**
     * 获取第一条记录
     * 
     * @return mixed
     */
    abstract public function first(): mixed;
}