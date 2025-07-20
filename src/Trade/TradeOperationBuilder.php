<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 交易操作构建器基类
 * 
 * 提供交易操作构建器的基础功能和接口定义
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class TradeOperationBuilder
{
    protected ?LunaTrade $trade = null;
    protected ?Transaction $transaction = null;
    protected array $context = [];
    
    public function __construct()
    {
        $this->trade = luna_trade();
    }
    
    /**
     * 设置要操作的交易
     * 
     * @param Transaction|int|string $transaction
     * @return $this
     */
    abstract public function transaction(Transaction|int|string $transaction): static;
    
    /**
     * 设置上下文信息
     * 
     * @param array $context
     * @return $this
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
    
    /**
     * 设置单个上下文值
     * 
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }
    
    /**
     * 更新交易状态
     * 
     * @param int $status
     * @return StatusChangeResult
     */
    abstract public function updateStatus(int $status): StatusChangeResult;
    
    /**
     * 完成交易
     * 
     * @return void
     */
    abstract public function complete(): void;
    
    /**
     * 取消交易
     * 
     * @param string $reason
     * @return void
     */
    abstract public function cancel(string $reason): void;
}