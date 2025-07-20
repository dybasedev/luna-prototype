<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

use Dybasedev\LunaPrototype\Trade\TradableHandler;
use Dybasedev\LunaPrototype\Trade\Tradable;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionContext;

/**
 * 标准可交易对象处理器
 * 
 * 提供标准的价格计算、格式化、验证等功能的实现。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardTradableHandler extends TradableHandler
{
    /**
     * 获取处理器名称
     * 
     * @return string
     */
    public function handlerName(): string
    {
        return 'standard-tradable';
    }
    
    /**
     * 获取处理器描述
     * 
     * @return string
     */
    public function handlerDescription(): string
    {
        return '标准可交易对象处理器';
    }
    
    /**
     * 格式化展示信息
     * 
     * @param Tradable $tradable 可交易对象
     * @param array $options 格式化选项
     * @return array
     */
    public function formatForDisplay(Tradable $tradable, array $options = []): array
    {
        return $this->getDisplayInfo($tradable, $options);
    }
    
    /**
     * 计算价格
     * 
     * 标准实现仅返回基础价格信息，不包含任何折扣计算。
     * 如需折扣功能，请使用 TransactionPreview 的 AmountModifier 机制。
     * 
     * @param Tradable $tradable 可交易对象
     * @param float $quantity 数量
     * @param TransactionContext|null $context 交易上下文
     * @return array{price: float, origin_price: float, unit_id: string|int|null, metadata: array}
     */
    public function calculatePrice(
        Tradable $tradable,
        float $quantity = 1.0,
        ?TransactionContext $context = null
    ): array {
        $unitPrice = $tradable->getTradablePrice();
        $originUnitPrice = $tradable->getTradableOriginPrice();
        
        return [
            'price' => round($unitPrice * $quantity, 2),
            'origin_price' => round($originUnitPrice * $quantity, 2),
            'unit_id' => $tradable->getTradablePriceUnit(),
            'metadata' => [
                'unit_price' => round($unitPrice, 2),
                'origin_unit_price' => round($originUnitPrice, 2),
                'quantity' => $quantity,
            ],
        ];
    }
    
    /**
     * 格式化价格显示
     * 
     * @param float $price 价格
     * @param string|int|null $unitId 单位ID
     * @param array $options 格式化选项
     * @return string
     */
    public function formatPrice(float $price, string|int|null $unitId = null, array $options = []): string
    {
        $decimals = $options['decimals'] ?? 2;
        $decimalSeparator = $options['decimal_separator'] ?? '.';
        $thousandsSeparator = $options['thousands_separator'] ?? ',';
        $prefix = $options['prefix'] ?? '¥';
        $suffix = $options['suffix'] ?? '';
        
        $formatted = number_format($price, $decimals, $decimalSeparator, $thousandsSeparator);
        
        // 如果有单位转换组件，可以在这里进行单位转换
        if ($unitId && function_exists('luna_unit_conversion')) {
            try {
                $unitConversion = luna_unit_conversion();
                $unit = $unitConversion->getUnit($unitId);
                if ($unit) {
                    $suffix = ' ' . $unit->symbol;
                }
            } catch (\Throwable $e) {
                // 忽略单位转换错误
            }
        }
        
        return $prefix . $formatted . $suffix;
    }
    
    /**
     * 验证可交易对象
     * 
     * @param Tradable $tradable 可交易对象
     * @param float $quantity 数量
     * @param TransactionContext|null $context 交易上下文
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateTradable(Tradable $tradable, float $quantity = 1.0, ?TransactionContext $context = null): array
    {
        $errors = [];
        
        // 检查是否可用
        if (!$tradable->isTradableAvailable()) {
            $errors[] = '商品暂时不可用';
        }
        
        // 检查库存
        if (!$tradable->checkTradableStock($quantity)) {
            $errors[] = '库存不足';
        }
        
        // 检查数量
        if ($quantity <= 0) {
            $errors[] = '数量必须大于0';
        }
        
        // 检查价格
        if ($tradable->getTradablePrice() < 0) {
            $errors[] = '价格无效';
        }
        
        // 上下文相关的验证
        if ($context) {
            // 检查购买限制
            if ($maxQuantity = $context->getParameter('max_quantity')) {
                if ($quantity > $maxQuantity) {
                    $errors[] = sprintf('超过最大购买数量限制（%s）', $maxQuantity);
                }
            }
            
            // 检查最小购买数量
            if ($minQuantity = $context->getParameter('min_quantity')) {
                if ($quantity < $minQuantity) {
                    $errors[] = sprintf('未达到最小购买数量（%s）', $minQuantity);
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * 获取可交易对象的展示信息
     * 
     * @param Tradable $tradable 可交易对象
     * @param array $options 选项
     * @return array
     */
    public function getDisplayInfo(Tradable $tradable, array $options = []): array
    {
        $priceInfo = $this->calculatePrice($tradable);
        
        $info = [
            'id' => $tradable->getTradableId(),
            'type' => $tradable->getTradableType(),
            'name' => $tradable->getTradableName(),
            'description' => $tradable->getTradableDescription(),
            'price' => $priceInfo['metadata']['unit_price'],
            'origin_price' => $priceInfo['metadata']['origin_unit_price'],
            'price_formatted' => $this->formatPrice($priceInfo['metadata']['unit_price'], $tradable->getTradablePriceUnit()),
            'origin_price_formatted' => $this->formatPrice($priceInfo['metadata']['origin_unit_price'], $tradable->getTradablePriceUnit()),
            'available' => $tradable->isTradableAvailable(),
            'payload' => $tradable->getTradablePayload(),
        ];
        
        // 添加提供商信息
        if ($provider = $tradable->getTradableProvider()) {
            $info['provider'] = $provider;
        }
        
        return $info;
    }
    
    /**
     * 交易支付后的处理
     * 
     * @param Tradable $tradable 可交易对象
     * @param Transaction $transaction 交易实例
     * @param float $quantity 数量
     * @param array $paymentInfo 支付信息
     * @return void
     */
    public function afterTransactionPaid(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        array $paymentInfo = []
    ): void {
        // 标准实现：记录日志
        logger()->info('Transaction paid for tradable', [
            'tradable_id' => $tradable->getTradableId(),
            'tradable_type' => $tradable->getTradableType(),
            'transaction_id' => $transaction->getTransactionId(),
            'quantity' => $quantity,
            'payment_info' => $paymentInfo,
        ]);
    }
    
    /**
     * 交易完成后的处理
     * 
     * @param Tradable $tradable 可交易对象
     * @param Transaction $transaction 交易实例
     * @param float $quantity 数量
     * @return void
     */
    public function afterTransactionCompleted(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity
    ): void {
        // 标准实现：记录日志
        logger()->info('Transaction completed for tradable', [
            'tradable_id' => $tradable->getTradableId(),
            'tradable_type' => $tradable->getTradableType(),
            'transaction_id' => $transaction->getTransactionId(),
            'quantity' => $quantity,
        ]);
    }
    
    /**
     * 交易取消后的处理
     * 
     * @param Tradable $tradable 可交易对象
     * @param Transaction $transaction 交易实例
     * @param float $quantity 数量
     * @param string $reason 取消原因
     * @return void
     */
    public function afterTransactionCanceled(
        Tradable $tradable,
        Transaction $transaction,
        float $quantity,
        string $reason
    ): void {
        // 标准实现：记录日志
        logger()->info('Transaction canceled for tradable', [
            'tradable_id' => $tradable->getTradableId(),
            'tradable_type' => $tradable->getTradableType(),
            'transaction_id' => $transaction->getTransactionId(),
            'quantity' => $quantity,
            'reason' => $reason,
        ]);
    }
}