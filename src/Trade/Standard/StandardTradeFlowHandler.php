<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Trade\TradeFlowHandler;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\StatusChangeResult;
use Dybasedev\LunaPrototype\Trade\Tradable;
use Dybasedev\LunaPrototype\Trade\TradableItem;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;
use Dybasedev\LunaPrototype\Trade\TransactionContext;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransactionTradable;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 标准交易流程处理器
 * 
 * 这是一个标准的交易流程处理器实现，适用于大多数常见的电商交易场景。
 * 提供了完整的交易生命周期管理，包括创建、支付、发货、完成、取消等状态。
 * 
 * 使用 TransactionStatus 对象管理状态，支持状态进入和离开的回调处理。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardTradeFlowHandler extends TradeFlowHandler
{
    /**
     * @var StandardTransactionStatusManager 状态管理器
     */
    protected StandardTransactionStatusManager $statusManager;
    
    /**
     * @var PaymentProvider|null 支付提供者
     */
    protected ?PaymentProvider $paymentProvider = null;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->statusManager = new StandardTransactionStatusManager();
    }
    
    /**
     * 设置支付提供者
     * 
     * @param PaymentProvider $provider
     * @return $this
     */
    public function setPaymentProvider(PaymentProvider $provider): static
    {
        $this->paymentProvider = $provider;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getPaymentProvider(): ?PaymentProvider
    {
        return $this->paymentProvider;
    }
    
    /**
     * @inheritDoc
     */
    public function handlerName(): string
    {
        return 'standard-trade-flow';
    }
    
    /**
     * @inheritDoc
     */
    public function handlerDescription(): string
    {
        return '适用于标准电商交易场景的交易流程处理器';
    }
    
    /**
     * @inheritDoc
     */
    public function generatePreview(
        SessionHolder $owner,
        Tradable|array $tradables,
        ?TransactionContext $context = null
    ): TransactionPreview {
        $preview = new TransactionPreview($owner);
        
        if ($context) {
            $preview->withContext($context);
            if ($channel = $context->getChannel()) {
                $preview->fromChannel($channel);
            }
        }
        
        // 添加交易项目
        $tradables = is_array($tradables) ? $tradables : [$tradables];
        foreach ($tradables as $tradable) {
            $quantity = $context?->getParameter('quantities.' . $tradable->getTradableId()) ??
                       $context?->getParameter('quantity') ?? 1.0;
            $preview->addItem($tradable, $quantity);
        }
        
        // 设置过期时间
        $expireMinutes = $context?->getParameter('expire_minutes') ?? 30;
        $preview->expiresAt($expireMinutes);
        
        // 应用渠道优惠
        if ($context && $campaignId = $context->getCampaignId()) {
            // 这里可以根据活动ID应用优惠
            // $preview->applyPromotion($campaignId, [...]);
        }
        
        // 应用支付方式影响（如果设置了支付方式）
        if ($context && $paymentMethod = $context->getParameter('payment_method')) {
            $this->applyPaymentMethodToPreview($preview, $paymentMethod, $context);
        }
        
        return $preview;
    }
    
    /**
     * @inheritDoc
     */
    public function createTransactionFromPreview(
        TransactionPreview $preview,
        array $confirmation = []
    ): Transaction {
        // 检查预览是否过期
        if ($preview->isExpired()) {
            throw LunaException::create('Transaction preview expired')
                ->withDisplayMessage('交易预览已过期')
                ->withHttpStatus(400);
        }
        
        // 验证交易
        $tradables = $preview->getItems()->map(fn($item) => $item->getTradable());
        $this->validateTransaction($preview->getOwner(), $tradables, $confirmation);
        
        // 创建交易记录
        $transaction = new TradeTransaction();
        $transaction->owner_id = $preview->getOwner()->getOperatorId();
        $transaction->owner_type = $preview->getOwner()->getOperatorType();
        $transaction->handler_id = $this->handlerId;
        $transaction->status = $this->statusManager->getInitialStatus()->code;
        $transaction->amount = $preview->getAmount();
        $transaction->origin_amount = $preview->getOriginAmount();
        $transaction->multi_tradables = $preview->getItems()->count() > 1;
        $transaction->payload = array_merge($confirmation['payload'] ?? [], [
            'preview_data' => $preview->toArray(),
            'confirmation' => $confirmation,
        ]);
        
        // 设置过期时间
        $transaction->expired_at = $preview->getExpiresAt();
        
        // 处理供应商信息
        if (isset($confirmation['provider'])) {
            $transaction->provider_id = $confirmation['provider']['provider_id'];
            $transaction->provider_type = $confirmation['provider']['provider_type'];
        }
        
        // 如果只有一个交易对象，直接关联
        if ($preview->getItems()->count() === 1) {
            $item = $preview->getItems()->first();
            $transaction->tradable_id = $item->getTradable()->getTradableId();
            $transaction->tradable_type = hash_code($item->getTradable()->getTradableType());
            $transaction->quantity = $item->getQuantity();
        }
        
        $transaction->save();
        
        // 创建交易对象关联记录
        foreach ($preview->getItems() as $item) {
            $tradablePivot = new TradeTransactionTradable();
            $tradablePivot->transaction_id = $transaction->id;
            $tradablePivot->tradable_id = $item->getTradable()->getTradableId();
            $tradablePivot->tradable_type = hash_code($item->getTradable()->getTradableType());
            $tradablePivot->amount = $item->getAmount();
            $tradablePivot->origin_amount = $item->getOriginAmount();
            $tradablePivot->unit_id = $item->getTradable()->getTradablePriceUnit();
            $tradablePivot->setQuantity($item->getQuantity());
            $tradablePivot->save();
        }
        
        return $transaction;
    }
    
    /**
     * @inheritDoc
     */
    public function handleStatusChange(
        Transaction $transaction,
        int $fromStatus,
        int $toStatus,
        array $context = []
    ): StatusChangeResult {
        // 获取状态对象
        $fromStatusObj = $this->statusManager->getStatusByCode($fromStatus);
        $toStatusObj = $this->statusManager->getStatusByCode($toStatus);
        
        // 检查状态转换是否合法
        if (!$fromStatusObj->canTransitionTo($toStatusObj, $transaction, $context)) {
            return StatusChangeResult::failure(
                'Invalid status transition',
                [
                    'from_status' => $fromStatusObj->key,
                    'to_status' => $toStatusObj->key,
                ]
            );
        }
        
        try {
            // 触发状态离开事件
            $fromStatusObj->onLeaving($transaction, $toStatusObj, $context);
            
            // 触发状态到达事件
            $toStatusObj->onReached($transaction, $fromStatusObj, $context);
            
            // 处理特定状态转换的业务逻辑
            $resultData = [];
            switch ($toStatusObj->key) {
                case 'paid':
                    // 支付完成后的处理
                    $this->handlePaymentCompleted($transaction, $context);
                    break;
                    
                case 'completed':
                    // 交易完成后的处理
                    $this->handleCompleted($transaction, $context);
                    break;
            }
            
            // 特殊处理：如果是从待支付到已支付但有支付参数，说明可能需要继续支付流程
            if ($fromStatusObj->key === 'pending_payment' && $toStatusObj->key === 'paid' && isset($context['payment_params'])) {
                return StatusChangeResult::needsAction('continue_payment', [
                    'payment_params' => $context['payment_params'],
                    'message' => '请继续完成支付',
                ]);
            }
            
            return StatusChangeResult::success($resultData);
        } catch (\Exception $e) {
            return StatusChangeResult::failure(
                $e->getMessage(),
                ['exception' => get_class($e)],
                true // 允许重试
            );
        }
    }
    
    /**
     * @inheritDoc
     */
    public function calculateAmount(
        Collection $tradables,
        array $options = []
    ): array {
        $amount = 0;
        $originAmount = 0;
        $items = [];
        
        foreach ($tradables as $tradable) {
            $quantity = $options['quantities'][$tradable->getTradableId()] ?? 
                        $options['quantity'] ?? 1;
            
            $itemAmount = $tradable->getTradablePrice() * $quantity;
            $itemOriginAmount = $tradable->getTradableOriginPrice() * $quantity;
            
            $amount += $itemAmount;
            $originAmount += $itemOriginAmount;
            
            $items[] = [
                'tradable_id' => $tradable->getTradableId(),
                'tradable_type' => hash_code($tradable->getTradableType()),
                'tradable_name' => $tradable->getTradableName(),
                'quantity' => $quantity,
                'price' => $tradable->getTradablePrice(),
                'origin_price' => $tradable->getTradableOriginPrice(),
                'amount' => $itemAmount,
                'origin_amount' => $itemOriginAmount,
                'unit_id' => $tradable->getTradablePriceUnit(),
            ];
        }
        
        // 应用折扣
        if (isset($options['discount'])) {
            $discount = min($options['discount'], $amount);
            $amount -= $discount;
        }
        
        return [
            'amount' => round($amount, 2),
            'origin_amount' => round($originAmount, 2),
            'items' => $items,
            'discount' => $options['discount'] ?? 0,
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function validateTransaction(
        SessionHolder $owner,
        Collection $tradables,
        array $options = []
    ): void {
        if ($tradables->isEmpty()) {
            throw LunaException::create('No tradables provided')
                ->withDisplayMessage('没有提供可交易对象')
                ->withHttpStatus(400);
        }
        
        foreach ($tradables as $tradable) {
            // 检查可用性
            if (!$tradable->isTradableAvailable()) {
                throw LunaException::create('Tradable not available')
                    ->withDisplayMessage('交易对象不可用')
                    ->withData([
                        'tradable_id' => $tradable->getTradableId(),
                        'tradable_name' => $tradable->getTradableName(),
                    ])
                    ->withHttpStatus(400);
            }
            
            // 检查库存
            $quantity = $options['quantities'][$tradable->getTradableId()] ?? 
                        $options['quantity'] ?? 1;
            
            if (!$tradable->checkTradableStock($quantity)) {
                throw LunaException::create('Insufficient stock')
                    ->withDisplayMessage('库存不足')
                    ->withData([
                        'tradable_id' => $tradable->getTradableId(),
                        'tradable_name' => $tradable->getTradableName(),
                        'required_quantity' => $quantity,
                    ])
                    ->withHttpStatus(400);
            }
        }
    }
    
    /**
     * @inheritDoc
     */
    public function completeTransaction(
        Transaction $transaction,
        array $context = []
    ): void {
        $currentStatus = $this->statusManager->getStatusByCode($transaction->getStatus());
        
        // 标准流程中，支付后可以完成
        if ($currentStatus->key !== 'paid') {
            throw LunaException::create('Transaction cannot be completed')
                ->withDisplayMessage('交易状态不允许完成')
                ->withData(['current_status' => $currentStatus->key])
                ->withHttpStatus(400);
        }
        
        // 这里可以添加完成交易后的业务逻辑
        // 例如：发放积分、更新用户等级等
    }
    
    /**
     * @inheritDoc
     */
    public function cancelTransaction(
        Transaction $transaction,
        string $reason,
        array $context = []
    ): void {
        $currentStatus = $this->statusManager->getStatusByCode($transaction->getStatus());
        
        // 只有特定状态可以取消
        $allowedKeys = ['pending_payment', 'paid'];
        if (!in_array($currentStatus->key, $allowedKeys, true)) {
            throw LunaException::create('Transaction cannot be canceled')
                ->withDisplayMessage('当前状态不允许取消交易')
                ->withData(['current_status' => $currentStatus->key])
                ->withHttpStatus(400);
        }
        
        // 记录取消原因
        $context['reason'] = $reason;
    }
    
    /**
     * @inheritDoc
     */
    public function getTransactionStatuses(): array
    {
        $statuses = [];
        foreach ($this->statusManager->getAllStatuses() as $key => $status) {
            $statuses[$status->code] = $status->name;
        }
        return $statuses;
    }
    
    /**
     * 获取初始状态
     * 
     * @return int
     */
    public function getInitialStatus(): int
    {
        return $this->statusManager->getInitialStatus()->code;
    }
    
    /**
     * 获取完成状态
     * 
     * @return int
     */
    public function getCompletedStatus(): int
    {
        return $this->statusManager->getCompletedStatus()->code;
    }
    
    /**
     * 获取取消状态
     * 
     * @return int
     */
    public function getCanceledStatus(): int
    {
        return $this->statusManager->getCanceledStatus()->code;
    }
    
    /**
     * 获取过期时间（秒）
     * 
     * @return int|null
     */
    protected function getExpiresAt(): ?int
    {
        return null;
    }
    
    /**
     * @inheritDoc
     */
    public function isValidStatusTransition(int $fromStatus, int $toStatus): bool
    {
        return $this->statusManager->isValidTransition($fromStatus, $toStatus);
    }
    
    
    /**
     * 处理支付完成
     * 
     * @param Transaction $transaction
     * @param array $context
     * @return void
     */
    protected function handlePaymentCompleted(Transaction $transaction, array $context): void
    {
        // 扣减库存
        $this->deductStock($transaction);
        
        // 记录支付信息
        $payload = $transaction->getPayload() ?? [];
        $payload['payment'] = [
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => $context['payment_method'] ?? 'unknown',
            'payment_no' => $context['payment_no'] ?? null,
        ];
        $transaction->setPayload($payload);
    }
    
    /**
     * 处理交易完成
     * 
     * @param Transaction $transaction
     * @param array $context
     * @return void
     */
    protected function handleCompleted(Transaction $transaction, array $context): void
    {
        $payload = $transaction->getPayload() ?? [];
        $payload['completed'] = [
            'completed_at' => now()->toDateTimeString(),
            'completion_type' => $context['type'] ?? 'standard',
        ];
        $transaction->setPayload($payload);
        
        // 这里可以触发完成后的业务逻辑
        // 例如：发放积分、更新用户等级等
    }
    
    /**
     * 扣减库存
     * 
     * @param Transaction $transaction
     * @return void
     */
    protected function deductStock(Transaction $transaction): void
    {
        // 实际项目中应该处理库存扣减
        // 这里仅作示例
    }
    
    /**
     * 恢复库存
     * 
     * @param Transaction $transaction
     * @return void
     */
    protected function restoreStock(Transaction $transaction): void
    {
        // 实际项目中应该处理库存恢复
        // 这里仅作示例
    }
}