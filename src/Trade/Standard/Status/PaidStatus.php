<?php

namespace Dybasedev\LunaPrototype\Trade\Standard\Status;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

/**
 * 已支付状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard\Status
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class PaidStatus extends TransactionStatus
{
    protected string $key = 'paid';
    protected string $name = '已支付';
    protected string $description = '买家已完成支付';
    
    /**
     * 获取可以转换到的状态列表
     * 
     * @return array<string>
     */
    public function getAllowedTransitions(): array
    {
        return ['completed', 'canceled'];
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
        // 记录支付信息到 payload
        $payload = $transaction->payload ?: [];
        $payload['paid_at'] = now()->toDateTimeString();
        
        if (isset($context['payment_method'])) {
            $payload['payment_method'] = $context['payment_method'];
        }
        if (isset($context['payment_no'])) {
            $payload['payment_no'] = $context['payment_no'];
        }
        
        $transaction->payload = $payload;
        
        // 触发库存扣减等操作
        $this->deductStock($transaction);
    }
    
    /**
     * 扣减库存
     * 
     * @param TradeTransaction $transaction
     * @return void
     */
    protected function deductStock(TradeTransaction $transaction): void
    {
        // 这里应该调用实际的库存扣减逻辑
        // 可以通过事件或直接调用库存服务
        foreach ($transaction->tradables as $tradable) {
            // $stockService->deduct($tradable->tradable_id, $tradable->quantity);
        }
    }
}