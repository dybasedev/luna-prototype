<?php

namespace Dybasedev\LunaPrototype\Trade\Standard\Status;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

/**
 * 已取消状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard\Status
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class CanceledStatus extends TransactionStatus
{
    protected string $key = 'canceled';
    protected string $name = '已取消';
    protected string $description = '交易已取消';
    
    /**
     * 获取可以转换到的状态列表
     * 
     * @return array<string>
     */
    public function getAllowedTransitions(): array
    {
        return []; // 最终状态，不能再转换
    }
    
    /**
     * 检查是否是最终状态
     * 
     * @return bool
     */
    public function isFinal(): bool
    {
        return true;
    }
    
    /**
     * 状态到达时的处理
     * 
     * @param TradeTransaction $transaction
     * @param TransactionStatus|null $fromStatus
     * @param array $context
     * @return void
     */
    public function onReached(
        TradeTransaction $transaction,
        ?TransactionStatus $fromStatus,
        array $context = []
    ): void {
        // 记录取消原因
        $reason = $context['reason'] ?? '用户取消';
        $transaction->markAsCanceled($reason);
        $transaction->markAsFinished();
        
        // 释放相关资源
        $this->releaseResources($transaction);
    }
    
    /**
     * 释放相关资源
     * 
     * @param TradeTransaction $transaction
     * @return void
     */
    protected function releaseResources(TradeTransaction $transaction): void
    {
        // 如果之前已支付，记录需要退款
        if ($transaction->status === short_hash_code('paid')) {
            $payload = $transaction->payload ?: [];
            $payload['need_refund'] = true;
            $payload['refund_amount'] = $transaction->amount;
            $transaction->payload = $payload;
        }
        
        // 这里可以释放预留的库存等资源
        // 实际实现中应该调用库存服务等
    }
}