<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;

/**
 * 可交易对象处理器基类
 * 
 * 为可交易对象提供处理器支持，处理价格计算、展示格式化、交易流程回调等。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class TradableHandler extends BaseHandler
{
    /**
     * 计算价格
     * 
     * 根据上下文计算可交易对象的实际价格
     * 
     * @param Tradable $tradable 可交易对象
     * @param float $quantity 数量
     * @param TransactionContext|null $context 交易上下文
     * @return array{price: float, origin_price: float, unit_id: string|int|null, metadata: array}
     */
    abstract public function calculatePrice(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array;
    
    /**
     * 格式化价格显示
     * 
     * @param float $price 价格
     * @param string|int|null $unitId 单位ID
     * @param array $options 格式化选项
     * @return string
     */
    abstract public function formatPrice(
        float $price,
        string|int|null $unitId = null,
        array $options = []
    ): string;
    
    /**
     * 格式化可交易对象信息用于展示
     * 
     * @param Tradable $tradable
     * @param array $options
     * @return array
     */
    abstract public function formatForDisplay(Tradable $tradable, array $options = []): array;
    
    /**
     * 验证可交易对象是否可以被交易
     * 
     * @param Tradable $tradable
     * @param float $quantity
     * @param TransactionContext|null $context
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validate(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        // 基本验证：可用性
        if (!$tradable->isTradableAvailable()) {
            return [
                'valid' => false,
                'message' => '商品不可用',
                'code' => 'UNAVAILABLE',
            ];
        }
        
        // 基本验证：库存
        if (!$tradable->checkTradableStock($quantity)) {
            return [
                'valid' => false,
                'message' => '库存不足',
                'code' => 'INSUFFICIENT_STOCK',
            ];
        }
        
        return [
            'valid' => true,
            'message' => null,
            'code' => null,
        ];
    }
    
    /**
     * 交易创建前的处理
     * 
     * @param Tradable $tradable
     * @param float $quantity
     * @param TransactionContext|null $context
     * @return void
     */
    public function beforeTransactionCreate(
        Tradable $tradable,
        float $quantity,
        ?TransactionContext $context = null
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 交易创建后的处理
     * 
     * @param Tradable $tradable
     * @param Transaction $transaction
     * @param float $quantity
     * @return void
     */
    public function afterTransactionCreated(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 交易支付后的处理
     * 
     * @param Tradable $tradable
     * @param Transaction $transaction
     * @param float $quantity
     * @param array $paymentInfo
     * @return void
     */
    public function afterTransactionPaid(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $paymentInfo = []
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 交易完成后的处理
     * 
     * @param Tradable $tradable
     * @param Transaction $transaction
     * @param float $quantity
     * @return void
     */
    public function afterTransactionCompleted(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 交易取消后的处理
     * 
     * @param Tradable $tradable
     * @param Transaction $transaction
     * @param float $quantity
     * @param string $reason
     * @return void
     */
    public function afterTransactionCanceled(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        string $reason
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 交易退款后的处理
     * 
     * @param Tradable $tradable
     * @param Transaction $transaction
     * @param float $quantity
     * @param array $refundInfo
     * @return void
     */
    public function afterTransactionRefunded(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $refundInfo = []
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 获取可交易对象的元数据
     * 
     * @param Tradable $tradable
     * @param array $fields 需要的字段
     * @return array
     */
    public function getMetadata(Tradable $tradable, array $fields = []): array
    {
        $metadata = [
            'id' => $tradable->getTradableId(),
            'type' => $tradable->getTradableType(),
            'name' => $tradable->getTradableName(),
            'description' => $tradable->getTradableDescription(),
            'price' => $tradable->getTradablePrice(),
            'origin_price' => $tradable->getTradableOriginPrice(),
            'price_unit' => $tradable->getTradablePriceUnit(),
            'available' => $tradable->isTradableAvailable(),
            'payload' => $tradable->getTradablePayload(),
        ];
        
        if (!empty($fields)) {
            return array_intersect_key($metadata, array_flip($fields));
        }
        
        return $metadata;
    }
    
    /**
     * 检查是否支持特定的支付方式
     * 
     * @param Tradable $tradable
     * @param string $paymentMethod
     * @param TransactionContext|null $context
     * @return bool
     */
    public function supportsPaymentMethod(
        Tradable $tradable,
        string $paymentMethod,
        ?TransactionContext $context = null
    ): bool {
        // 默认支持所有支付方式
        // 子类可以重写此方法实现具体的支付方式限制
        return true;
    }
}