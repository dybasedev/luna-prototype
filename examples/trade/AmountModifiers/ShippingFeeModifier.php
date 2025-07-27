<?php

namespace Examples\Trade\AmountModifiers;

use Dybasedev\LunaPrototype\Trade\AmountModifier;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;

/**
 * 运费修饰器示例
 * 
 * 展示如何实现运费计算，包括满额免运费、按重量计算等。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class ShippingFeeModifier extends AmountModifier
{
    /**
     * @var float 基础运费
     */
    protected float $baseFee;
    
    /**
     * @var float 免运费门槛金额
     */
    protected float $freeShippingThreshold;
    
    /**
     * @var array 地区运费配置
     */
    protected array $regionalRates;
    
    /**
     * 构造函数
     * 
     * @param string $id 唯一标识
     * @param float $baseFee 基础运费
     * @param float $freeThreshold 免运费门槛
     * @param array $regionalRates 地区费率配置
     */
    public function __construct(
        string $id = 'shipping',
        float $baseFee = 10.0,
        float $freeThreshold = 99.0,
        array $regionalRates = []
    ) {
        parent::__construct($id, '运费', 'fee');
        
        $this->baseFee = $baseFee;
        $this->freeShippingThreshold = $freeThreshold;
        $this->regionalRates = $regionalRates;
        
        // 运费通常最后计算
        $this->setPriority(200);
    }
    
    /**
     * @inheritDoc
     */
    public function calculate(float $amount, TransactionPreview $preview): float
    {
        // 检查是否满足免运费条件
        if ($this->isEligibleForFreeShipping($preview)) {
            return $amount;
        }
        
        $shippingFee = $this->calculateShippingFee($preview);
        
        return $amount + $shippingFee;
    }
    
    /**
     * @inheritDoc
     */
    public function canApply(TransactionPreview $preview): bool
    {
        // 检查是否需要配送（虚拟商品不需要运费）
        $needsShipping = false;
        
        foreach ($preview->getItems() as $item) {
            $tradable = $item->getTradable();
            
            // 假设 Tradable 有 isPhysical 方法
            if (!method_exists($tradable, 'isPhysical') || $tradable->isPhysical()) {
                $needsShipping = true;
                break;
            }
        }
        
        return $needsShipping;
    }
    
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        if ($this->freeShippingThreshold > 0) {
            return sprintf(
                '运费 ¥%.2f（满 ¥%.2f 免运费）',
                $this->baseFee,
                $this->freeShippingThreshold
            );
        }
        
        return sprintf('运费 ¥%.2f', $this->baseFee);
    }
    
    /**
     * 检查是否符合免运费条件
     * 
     * @param TransactionPreview $preview
     * @return bool
     */
    protected function isEligibleForFreeShipping(TransactionPreview $preview): bool
    {
        // 检查金额是否达到免运费门槛
        if ($this->freeShippingThreshold > 0 && 
            $preview->getBaseAmount() >= $this->freeShippingThreshold) {
            return true;
        }
        
        // 检查是否有免运费优惠券
        foreach ($preview->getModifiers() as $modifier) {
            if ($modifier->getMetadata('provides_free_shipping') === true) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 计算运费
     * 
     * @param TransactionPreview $preview
     * @return float
     */
    protected function calculateShippingFee(TransactionPreview $preview): float
    {
        $fee = $this->baseFee;
        
        // 获取配送地址信息
        $shippingAddress = $preview->getMetadata()['shipping_address'] ?? null;
        
        if ($shippingAddress && isset($shippingAddress['region'])) {
            $region = $shippingAddress['region'];
            
            // 应用地区费率
            if (isset($this->regionalRates[$region])) {
                $regionalRate = $this->regionalRates[$region];
                
                if (is_array($regionalRate)) {
                    // 支持复杂的地区费率配置
                    $fee = $regionalRate['base'] ?? $fee;
                    
                    // 按重量计费
                    if (isset($regionalRate['per_kg'])) {
                        $totalWeight = $this->calculateTotalWeight($preview);
                        $fee += $totalWeight * $regionalRate['per_kg'];
                    }
                } else {
                    $fee = $regionalRate;
                }
            }
        }
        
        // 应用促销活动折扣
        $shippingDiscount = $this->getMetadata('shipping_discount', 0);
        if ($shippingDiscount > 0) {
            $fee = max(0, $fee - $shippingDiscount);
        }
        
        return $fee;
    }
    
    /**
     * 计算总重量（示例方法）
     * 
     * @param TransactionPreview $preview
     * @return float
     */
    protected function calculateTotalWeight(TransactionPreview $preview): float
    {
        $totalWeight = 0.0;
        
        foreach ($preview->getItems() as $item) {
            $tradable = $item->getTradable();
            
            // 假设 Tradable 有 getWeight 方法
            if (method_exists($tradable, 'getWeight')) {
                $totalWeight += $tradable->getWeight() * $item->getQuantity();
            }
        }
        
        return $totalWeight;
    }
    
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'base_fee' => $this->baseFee,
            'free_shipping_threshold' => $this->freeShippingThreshold,
            'regional_rates' => $this->regionalRates,
        ]);
    }
    
    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $modifier = new static(
            $data['id'] ?? 'shipping',
            $data['base_fee'] ?? 10.0,
            $data['free_shipping_threshold'] ?? 99.0,
            $data['regional_rates'] ?? []
        );
        
        if (isset($data['priority'])) {
            $modifier->setPriority($data['priority']);
        }
        
        if (isset($data['metadata'])) {
            $modifier->withMetadata($data['metadata']);
        }
        
        return $modifier;
    }
}