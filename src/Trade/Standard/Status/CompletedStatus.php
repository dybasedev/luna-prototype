<?php

namespace Dybasedev\LunaPrototype\Trade\Standard\Status;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

/**
 * 已完成状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard\Status
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class CompletedStatus extends TransactionStatus
{
    protected(set) string $key = 'completed';
    protected(set) string $name = '已完成';
    protected(set) string $description = '交易已完成';

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
        // 标记交易完成
        $transaction->markAsCompleted();
        $transaction->markAsFinished();
        
        // 触发完成后的操作
        $this->afterCompleted($transaction);
    }
    
    /**
     * 交易完成后的处理
     * 
     * @param TradeTransaction $transaction
     * @return void
     */
    protected function afterCompleted(TradeTransaction $transaction): void
    {
        // 可以在这里触发：
        // 1. 积分发放
        // 2. 会员成长值
        // 3. 销售统计更新
        // 4. 发送完成通知等
    }
}