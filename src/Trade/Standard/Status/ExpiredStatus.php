<?php

namespace Dybasedev\LunaPrototype\Trade\Standard\Status;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

/**
 * 已过期状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard\Status
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class ExpiredStatus extends TransactionStatus
{
    protected(set) string $key = 'expired';
    protected(set) string $name = '已过期';
    protected(set) string $description = '交易已过期，未在规定时间内完成支付';
    
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
        // 标记交易结束
        $transaction->markAsFinished();
        
        // 记录过期信息
        $payload = $transaction->payload ?: [];
        $payload['expired_at'] = now()->toDateTimeString();
        $payload['expire_reason'] = $context['reason'] ?? '支付超时';
        $transaction->payload = $payload;
        
        // 释放库存等资源
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
        // 这里可以释放预留的库存等资源
        // 实际实现中应该调用库存服务等
    }
}