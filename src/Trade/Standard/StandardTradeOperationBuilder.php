<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\LunaTrade;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;
use Dybasedev\LunaPrototype\Trade\StatusChangeResult;
use Dybasedev\LunaPrototype\Trade\TradeOperationBuilder;
use Dybasedev\LunaPrototype\Trade\Transaction;

/**
 * 标准交易操作构建器
 * 
 * 为标准交易流程提供链式调用接口来构建和执行交易操作
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardTradeOperationBuilder extends TradeOperationBuilder
{
    protected ?TradeTransaction $transaction = null;
    
    /**
     * 设置要操作的交易
     * 
     * @param TradeTransaction|int $transaction
     * @return $this
     */
    public function transaction(Transaction|int|string $transaction): static
    {
        if (is_int($transaction) || is_string($transaction)) {
            $this->transaction = $this->trade?->getTransaction($transaction);
        } elseif ($transaction instanceof TradeTransaction) {
            $this->transaction = $transaction;
        }
        
        return $this;
    }
    
    
    /**
     * 更新交易状态
     * 
     * @param int $status
     * @return StatusChangeResult
     * @throws \Dybasedev\LunaPrototype\Foundation\Exception\LunaException
     */
    public function updateStatus(int $status): StatusChangeResult
    {
        if (!$this->transaction || !$this->trade) {
            return StatusChangeResult::failure('No transaction or trade instance available');
        }
        
        $result = $this->trade->updateTransactionStatus(
            $this->transaction,
            $status,
            $this->context
        );
        
        return $result;
    }
    
    /**
     * 完成交易
     * 
     * @return void
     * @throws \Dybasedev\LunaPrototype\Foundation\Exception\LunaException
     */
    public function complete(): void
    {
        if (!$this->transaction || !$this->trade) {
            return;
        }
        
        $this->trade->completeTransaction($this->transaction, $this->context);
    }
    
    /**
     * 取消交易
     * 
     * @param string $reason
     * @return void
     * @throws \Dybasedev\LunaPrototype\Foundation\Exception\LunaException
     */
    public function cancel(string $reason): void
    {
        if (!$this->transaction || !$this->trade) {
            return;
        }
        
        $this->trade->cancelTransaction($this->transaction, $reason, $this->context);
    }
}