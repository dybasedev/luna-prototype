<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionContext;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;

/**
 * 支付方式接口
 * 
 * 定义了支付方式的基本行为，所有支付方式都需要实现此接口
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface PaymentMethod
{
    /**
     * 获取支付方式的唯一标识符
     * 
     * @return string
     */
    public function getName(): string;
    
    /**
     * 获取支付方式的显示名称
     * 
     * @return string
     */
    public function getDisplayName(): string;
    
    /**
     * 获取支付方式的描述
     * 
     * @return string|null
     */
    public function getDescription(): ?string;
    
    /**
     * 获取支付方式的图标
     * 
     * @return string|null
     */
    public function getIcon(): ?string;
    
    /**
     * 检查支付方式是否可用
     * 
     * @param SessionHolder $owner 交易所有者
     * @param TransactionContext|null $context 交易上下文
     * @return bool
     */
    public function isAvailable(SessionHolder $owner, ?TransactionContext $context = null): bool;
    
    /**
     * 获取支付方式的可用性信息
     * 
     * @param SessionHolder $owner 交易所有者
     * @param TransactionContext|null $context 交易上下文
     * @return array{available: bool, reason?: string, metadata?: array}
     */
    public function getAvailability(SessionHolder $owner, ?TransactionContext $context = null): array;
    
    /**
     * 验证支付参数
     * 
     * @param array $parameters 支付参数
     * @return array{valid: bool, errors?: array<string, string>}
     */
    public function validateParameters(array $parameters): array;
    
    /**
     * 计算支付金额（考虑支付方式的优惠等）
     * 
     * @param float $amount 原始金额
     * @param SessionHolder $owner 交易所有者
     * @param TransactionContext|null $context 交易上下文
     * @return array{
     *     amount: float,
     *     original_amount: float,
     *     discount: float,
     *     discount_description?: string,
     *     metadata?: array
     * }
     */
    public function calculateAmount(
        float $amount,
        SessionHolder $owner,
        ?TransactionContext $context = null
    ): array;
    
    /**
     * 检查是否支持预览阶段的金额计算
     * 
     * @return bool
     */
    public function supportsPreviewCalculation(): bool;
    
    /**
     * 在交易预览阶段计算影响
     * 
     * @param TransactionPreview $preview 交易预览
     * @param SessionHolder $owner 交易所有者
     * @param TransactionContext|null $context 交易上下文
     * @return array 返回需要应用的修饰器或其他影响
     */
    public function applyToPreview(
        TransactionPreview $preview,
        SessionHolder $owner,
        ?TransactionContext $context = null
    ): array;
    
    /**
     * 发起支付
     * 
     * @param Transaction $transaction 交易对象
     * @param array $parameters 支付参数
     * @param TransactionContext|null $context 交易上下文
     * @return PaymentResult
     */
    public function pay(
        Transaction $transaction,
        array $parameters = [],
        ?TransactionContext $context = null
    ): PaymentResult;
    
    /**
     * 处理支付回调
     * 
     * @param array $data 回调数据
     * @param TransactionContext|null $context 交易上下文
     * @return PaymentResult
     */
    public function handleCallback(array $data, ?TransactionContext $context = null): PaymentResult;
    
    /**
     * 查询支付状态
     * 
     * @param Transaction $transaction 交易对象
     * @param array $parameters 查询参数
     * @return PaymentResult
     */
    public function queryStatus(Transaction $transaction, array $parameters = []): PaymentResult;
    
    /**
     * 发起退款
     * 
     * @param Transaction $transaction 交易对象
     * @param float $amount 退款金额
     * @param string $reason 退款原因
     * @param array $parameters 退款参数
     * @return PaymentResult
     */
    public function refund(
        Transaction $transaction,
        float $amount,
        string $reason,
        array $parameters = []
    ): PaymentResult;
    
    /**
     * 获取支付方式的配置选项
     * 
     * @return array
     */
    public function getConfiguration(): array;
    
    /**
     * 获取支付方式支持的功能
     * 
     * @return array{
     *     supports_partial_payment: bool,
     *     supports_refund: bool,
     *     supports_query: bool,
     *     supports_callback: bool,
     *     requires_redirect: bool,
     *     instant_payment: bool
     * }
     */
    public function getCapabilities(): array;
}