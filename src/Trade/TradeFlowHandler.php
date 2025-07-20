<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentProvider;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentMethod;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentResult;
use Illuminate\Support\Collection;

/**
 * 交易流程处理器基类
 * 
 * 这是所有交易流程处理器的基类，定义了交易流程的核心行为。
 * 子类需要实现具体的交易流程逻辑，以适应不同的业务场景。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class TradeFlowHandler extends BaseHandler
{
    /**
     * @var TransactionNumberGenerator|null 交易编号生成器
     */
    protected ?TransactionNumberGenerator $transactionNumberGenerator = null;
    
    /**
     * 获取交易编号生成器
     * 
     * 如果未设置，则使用全局默认生成器
     * 
     * @return TransactionNumberGenerator
     */
    public function getTransactionNumberGenerator(): TransactionNumberGenerator
    {
        if ($this->transactionNumberGenerator === null) {
            // 尝试从配置获取全局默认生成器
            $configure = app(LunaTradeConfigure::class);
            $this->transactionNumberGenerator = $configure->getTransactionNumberGenerator();
        }
        
        return $this->transactionNumberGenerator;
    }
    
    /**
     * 设置交易编号生成器
     * 
     * @param TransactionNumberGenerator $generator
     * @return $this
     */
    public function setTransactionNumberGenerator(TransactionNumberGenerator $generator): static
    {
        $this->transactionNumberGenerator = $generator;
        return $this;
    }
    
    /**
     * 生成交易预览
     * 
     * @param SessionHolder $owner 交易所有者
     * @param Tradable|Tradable[] $tradables 可交易对象
     * @param TransactionContext|null $context 交易上下文
     * @return TransactionPreview
     * @throws LunaException
     */
    abstract public function generatePreview(
        SessionHolder $owner,
        Tradable|array $tradables,
        ?TransactionContext $context = null
    ): TransactionPreview;
    
    /**
     * 从预览创建交易
     * 
     * @param TransactionPreview $preview 交易预览
     * @param array $confirmation 确认信息
     * @return Transaction 创建的交易实例
     * @throws LunaException
     */
    abstract public function createTransactionFromPreview(
        TransactionPreview $preview,
        array $confirmation = []
    ): Transaction;
    
    /**
     * 创建交易（便捷方法）
     * 
     * 直接创建交易的便捷方法，内部会自动生成预览并创建交易。
     * 适用于不需要预览步骤的简单场景。
     * 
     * 如果需要更精细的控制（如应用优惠券、查看预览信息等），
     * 请使用 generatePreview() 和 createTransactionFromPreview() 方法。
     * 
     * @param SessionHolder $owner 交易所有者
     * @param Tradable|Tradable[] $tradables 可交易对象（单个或数组）
     * @param array $options 额外选项，支持：
     *                      - context: TransactionContext 实例
     *                      - 其他参数将作为确认信息传递
     * @return Transaction 创建的交易实例
     * @throws LunaException
     */
    public function createTransaction(
        SessionHolder $owner,
        Tradable|array $tradables,
        array $options = []
    ): Transaction {
        // 使用新流程：先生成预览，再创建交易
        $context = $options['context'] ?? null;
        if ($context instanceof TransactionContext) {
            unset($options['context']);
        } else {
            // 如果没有上下文，创建一个临时的上下文来传递参数
            if (!empty($options)) {
                $context = TransactionContext::make()->withParameters($options);
            } else {
                $context = null;
            }
        }
        
        $preview = $this->generatePreview($owner, $tradables, $context);
        
        // 将 options 作为确认信息传递
        return $this->createTransactionFromPreview($preview, $options);
    }
    
    /**
     * 处理交易状态变更
     * 
     * @param Transaction $transaction 交易实例
     * @param int $fromStatus 原状态
     * @param int $toStatus 目标状态
     * @param array $context 上下文信息
     * @return StatusChangeResult 状态变更结果
     */
    abstract public function handleStatusChange(
        Transaction $transaction,
        int $fromStatus,
        int $toStatus,
        array $context = []
    ): StatusChangeResult;
    
    /**
     * 状态变更事件
     * 
     * 当状态变更成功后触发
     * 
     * @param Transaction $transaction
     * @param string|int $fromStatus
     * @param string|int $toStatus
     * @param TransactionContext|null $context
     * @return void
     */
    public function onStatusChanged(
        Transaction $transaction,
        string|int $fromStatus,
        string|int $toStatus,
        ?TransactionContext $context = null
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 计算交易金额
     * 
     * 根据可交易对象计算交易的总金额
     * 
     * @param Collection $tradables 可交易对象集合
     * @param array $options 计算选项
     * @return array{amount: float, origin_amount: float, items: array} 计算结果
     */
    abstract public function calculateAmount(
        Collection $tradables,
        array $options = []
    ): array;
    
    /**
     * 验证交易是否可以进行
     * 
     * @param SessionHolder $owner 交易所有者
     * @param Collection $tradables 可交易对象集合
     * @param array $options 验证选项
     * @return void
     * @throws LunaException 当验证失败时抛出
     */
    abstract public function validateTransaction(
        SessionHolder $owner,
        Collection $tradables,
        array $options = []
    ): void;
    
    /**
     * 完成交易
     * 
     * 处理交易完成的相关逻辑
     * 
     * @param Transaction $transaction 交易实例
     * @param array $context 上下文信息
     * @return void
     * @throws LunaException
     */
    abstract public function completeTransaction(
        Transaction $transaction,
        array $context = []
    ): void;
    
    /**
     * 取消交易
     * 
     * 处理交易取消的相关逻辑
     * 
     * @param Transaction $transaction 交易实例
     * @param string $reason 取消原因
     * @param array $context 上下文信息
     * @return void
     * @throws LunaException
     */
    abstract public function cancelTransaction(
        Transaction $transaction,
        string $reason,
        array $context = []
    ): void;
    
    /**
     * 获取交易状态列表
     * 
     * 返回此处理器支持的所有交易状态
     * 
     * @return array<int, string> 状态码 => 状态名称的映射
     */
    abstract public function getTransactionStatuses(): array;
    
    /**
     * 获取初始状态
     * 
     * @return int
     */
    public function getInitialStatus(): int
    {
        return 0;
    }
    
    /**
     * 获取完成状态
     * 
     * @return int
     */
    public function getCompletedStatus(): int
    {
        return 100;
    }
    
    /**
     * 获取取消状态
     * 
     * @return int
     */
    public function getCanceledStatus(): int
    {
        return -1;
    }
    
    /**
     * 检查状态变更是否合法
     * 
     * @param int $fromStatus 原状态
     * @param int $toStatus 目标状态
     * @return bool
     */
    public function isValidStatusTransition(int $fromStatus, int $toStatus): bool
    {
        // 默认实现：不允许从完成或取消状态变更到其他状态
        if (in_array($fromStatus, [$this->getCompletedStatus(), $this->getCanceledStatus()])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 处理交易过期
     * 
     * @param Transaction $transaction 交易实例
     * @return void
     */
    public function handleExpiredTransaction(Transaction $transaction): void
    {
        // 默认实现：过期就取消
        $this->cancelTransaction($transaction, 'expired', ['auto' => true]);
    }
    
    /**
     * 获取可交易对象的处理器
     * 
     * @param Tradable $tradable
     * @return TradableHandler|null
     */
    protected function getTradableHandler(Tradable $tradable): ?TradableHandler
    {
        // 子类可以重写此方法返回对应的处理器
        return null;
    }
    
    /**
     * 检查支付方式是否可用
     * 
     * @param string $paymentMethod
     * @param Transaction $transaction
     * @param TransactionContext|null $context
     * @return bool
     */
    public function isPaymentMethodAvailable(
        string $paymentMethod,
        Transaction $transaction,
        ?TransactionContext $context = null
    ): bool {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return false;
        }
        
        $method = $provider->get($paymentMethod);
        if (!$method) {
            return false;
        }
        
        return $method->isAvailable($transaction->getOwner(), $context);
    }
    
    /**
     * 获取支付提供者
     * 
     * @return PaymentProvider|null
     */
    protected function getPaymentProvider(): ?PaymentProvider
    {
        // 尝试从容器获取支付提供者
        if (app()->bound(PaymentProvider::class)) {
            return app(PaymentProvider::class);
        }
        
        return null;
    }
    
    /**
     * 获取可用的支付方式列表
     * 
     * @param Transaction $transaction
     * @param TransactionContext|null $context
     * @return array
     */
    public function getAvailablePaymentMethods(
        Transaction $transaction,
        ?TransactionContext $context = null
    ): array {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return [];
        }
        
        return $provider->getList($transaction->getOwner(), $context, true);
    }
    
    /**
     * 处理支付
     * 
     * @param Transaction $transaction
     * @param string $paymentMethod
     * @param array $parameters
     * @param TransactionContext|null $context
     * @return PaymentResult
     */
    public function processPayment(
        Transaction $transaction,
        string $paymentMethod,
        array $parameters = [],
        ?TransactionContext $context = null
    ): PaymentResult {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return PaymentResult::failure('Payment provider not configured');
        }
        
        $method = $provider->get($paymentMethod);
        if (!$method) {
            return PaymentResult::failure('Payment method not found');
        }
        
        // 检查支付方式是否可用
        if (!$method->isAvailable($transaction->getOwner(), $context)) {
            return PaymentResult::failure('Payment method not available');
        }
        
        // 执行支付
        return $method->pay($transaction, $parameters, $context);
    }
    
    /**
     * 处理支付回调
     * 
     * @param string $paymentMethod
     * @param array $data
     * @param TransactionContext|null $context
     * @return PaymentResult
     */
    public function handlePaymentCallback(
        string $paymentMethod,
        array $data,
        ?TransactionContext $context = null
    ): PaymentResult {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return PaymentResult::failure('Payment provider not configured');
        }
        
        $method = $provider->get($paymentMethod);
        if (!$method) {
            return PaymentResult::failure('Payment method not found');
        }
        
        return $method->handleCallback($data, $context);
    }
    
    /**
     * 查询支付状态
     * 
     * @param Transaction $transaction
     * @param string $paymentMethod
     * @param array $parameters
     * @return PaymentResult
     */
    public function queryPaymentStatus(
        Transaction $transaction,
        string $paymentMethod,
        array $parameters = []
    ): PaymentResult {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return PaymentResult::failure('Payment provider not configured');
        }
        
        $method = $provider->get($paymentMethod);
        if (!$method) {
            return PaymentResult::failure('Payment method not found');
        }
        
        return $method->queryStatus($transaction, $parameters);
    }
    
    /**
     * 处理退款
     * 
     * @param Transaction $transaction
     * @param string $paymentMethod
     * @param float $amount
     * @param string $reason
     * @param array $parameters
     * @return PaymentResult
     */
    public function processRefund(
        Transaction $transaction,
        string $paymentMethod,
        float $amount,
        string $reason,
        array $parameters = []
    ): PaymentResult {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return PaymentResult::failure('Payment provider not configured');
        }
        
        $method = $provider->get($paymentMethod);
        if (!$method) {
            return PaymentResult::failure('Payment method not found');
        }
        
        return $method->refund($transaction, $amount, $reason, $parameters);
    }
    
    /**
     * 在交易预览时应用支付方式的影响
     * 
     * @param TransactionPreview $preview
     * @param string $paymentMethod
     * @param TransactionContext|null $context
     * @return void
     */
    public function applyPaymentMethodToPreview(
        TransactionPreview $preview,
        string $paymentMethod,
        ?TransactionContext $context = null
    ): void {
        $provider = $this->getPaymentProvider();
        if (!$provider) {
            return;
        }
        
        $method = $provider->get($paymentMethod);
        if (!$method || !$method->supportsPreviewCalculation()) {
            return;
        }
        
        $impact = $method->applyToPreview($preview, $preview->getOwner(), $context);
        
        // 应用修饰器
        if (!empty($impact['modifiers'])) {
            foreach ($impact['modifiers'] as $modifier) {
                $preview->addModifier($modifier);
            }
        }
        
        // 设置元数据
        if (!empty($impact['metadata'])) {
            $preview->setMetadata(array_merge(
                $preview->getMetadata(),
                ['payment_method' => $impact['metadata']]
            ));
        }
    }
}