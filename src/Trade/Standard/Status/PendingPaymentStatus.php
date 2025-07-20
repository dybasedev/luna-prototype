<?php

namespace Dybasedev\LunaPrototype\Trade\Standard\Status;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

/**
 * 待支付状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard\Status
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class PendingPaymentStatus extends TransactionStatus
{
    protected string $key = 'pending_payment';
    protected string $name = '待支付';
    protected string $description = '交易已创建，等待买家支付';
    
    /**
     * 获取可以转换到的状态列表
     * 
     * @return array<string>
     */
    public function getAllowedTransitions(): array
    {
        return ['paid', 'canceled', 'expired'];
    }
    
    /**
     * 检查是否是初始状态
     * 
     * @return bool
     */
    public function isInitial(): bool
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
        // 设置支付过期时间到 payload
        if (isset($context['expire_minutes'])) {
            $payload = $transaction->payload ?: [];
            $payload['payment_expires_at'] = now()->addMinutes($context['expire_minutes'])->toDateTimeString();
            $transaction->payload = $payload;
        }
    }
    
    /**
     * 状态离开时的处理
     * 
     * @param TradeTransaction $transaction
     * @param TransactionStatus $toStatus
     * @param array $context
     * @return void
     */
    public function onLeaving(
        TradeTransaction $transaction,
        TransactionStatus $toStatus,
        array $context = []
    ): void {
        // 如果是支付成功，清除过期时间
        if ($toStatus->getKey() === 'paid') {
            $payload = $transaction->payload ?: [];
            unset($payload['payment_expires_at']);
            $transaction->payload = $payload;
        }
    }
}