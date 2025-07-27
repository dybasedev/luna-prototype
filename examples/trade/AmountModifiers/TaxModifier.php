<?php

namespace Examples\Trade\AmountModifiers;

use Dybasedev\LunaPrototype\Trade\AmountModifier;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;

/**
 * 税费修饰器示例
 * 
 * 展示如何实现税费计算，支持不同商品类型的差异化税率。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TaxModifier extends AmountModifier
{
    /**
     * @var float 默认税率
     */
    protected float $defaultTaxRate;
    
    /**
     * @var array 商品类型税率映射
     */
    protected array $categoryTaxRates;
    
    /**
     * @var bool 是否税费包含在价格中
     */
    protected bool $taxIncludedInPrice;
    
    /**
     * 构造函数
     * 
     * @param string $id 唯一标识
     * @param float $defaultRate 默认税率（百分比）
     * @param array $categoryRates 分类税率
     * @param bool $includedInPrice 是否含税
     */
    public function __construct(
        string $id = 'tax',
        float $defaultRate = 13.0,
        array $categoryRates = [],
        bool $includedInPrice = false
    ) {
        parent::__construct($id, '税费', 'tax');
        
        $this->defaultTaxRate = $defaultRate;
        $this->categoryTaxRates = $categoryRates;
        $this->taxIncludedInPrice = $includedInPrice;
        
        // 税费通常在折扣后、运费前计算
        $this->setPriority(150);
    }
    
    /**
     * @inheritDoc
     */
    public function calculate(float $amount, TransactionPreview $preview): float
    {
        $taxAmount = $this->calculateTax($preview);
        
        if ($this->taxIncludedInPrice) {
            // 价格已含税，不需要额外添加
            return $amount;
        }
        
        return $amount + $taxAmount;
    }
    
    /**
     * @inheritDoc
     */
    public function canApply(TransactionPreview $preview): bool
    {
        // 检查是否需要征税
        $taxExempt = $preview->getMetadata()['tax_exempt'] ?? false;
        if ($taxExempt) {
            return false;
        }
        
        // 检查地区是否需要征税
        $billingAddress = $preview->getMetadata()['billing_address'] ?? null;
        if ($billingAddress && !$this->isTaxableRegion($billingAddress)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        if ($this->taxIncludedInPrice) {
            return sprintf('含税（税率 %.1f%%）', $this->defaultTaxRate);
        }
        
        return sprintf('税费（%.1f%%）', $this->defaultTaxRate);
    }
    
    /**
     * 计算税费
     * 
     * @param TransactionPreview $preview
     * @return float
     */
    protected function calculateTax(TransactionPreview $preview): float
    {
        $taxableAmount = 0.0;
        $taxAmount = 0.0;
        
        // 按商品分别计算税费
        foreach ($preview->getItems() as $item) {
            $tradable = $item->getTradable();
            $itemAmount = $item->getAmount();
            
            // 获取商品税率
            $taxRate = $this->getTaxRateForItem($tradable);
            
            if ($taxRate > 0) {
                if ($this->taxIncludedInPrice) {
                    // 从含税价格中计算税额
                    $itemTax = $itemAmount - ($itemAmount / (1 + $taxRate / 100));
                } else {
                    // 计算应加的税额
                    $itemTax = $itemAmount * ($taxRate / 100);
                }
                
                $taxAmount += $itemTax;
                $taxableAmount += $itemAmount;
            }
        }
        
        // 将税费信息存储到元数据中
        $this->setMetadata('taxable_amount', $taxableAmount);
        $this->setMetadata('tax_amount', $taxAmount);
        
        return $taxAmount;
    }
    
    /**
     * 获取商品的税率
     * 
     * @param mixed $tradable
     * @return float
     */
    protected function getTaxRateForItem($tradable): float
    {
        // 检查商品是否免税
        if (method_exists($tradable, 'isTaxExempt') && $tradable->isTaxExempt()) {
            return 0.0;
        }
        
        // 获取商品类别
        if (method_exists($tradable, 'getCategory')) {
            $category = $tradable->getCategory();
            
            if (isset($this->categoryTaxRates[$category])) {
                return $this->categoryTaxRates[$category];
            }
        }
        
        return $this->defaultTaxRate;
    }
    
    /**
     * 检查地区是否需要征税
     * 
     * @param array $address
     * @return bool
     */
    protected function isTaxableRegion(array $address): bool
    {
        // 这里可以实现复杂的地区税务规则
        // 示例：某些地区可能免税
        $taxFreeRegions = $this->getMetadata('tax_free_regions', []);
        
        if (isset($address['region']) && in_array($address['region'], $taxFreeRegions)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 获取税费明细
     * 
     * @return array
     */
    public function getTaxBreakdown(): array
    {
        return [
            'taxable_amount' => $this->getMetadata('taxable_amount', 0),
            'tax_amount' => $this->getMetadata('tax_amount', 0),
            'tax_rate' => $this->defaultTaxRate,
            'included_in_price' => $this->taxIncludedInPrice,
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'default_tax_rate' => $this->defaultTaxRate,
            'category_tax_rates' => $this->categoryTaxRates,
            'tax_included_in_price' => $this->taxIncludedInPrice,
            'tax_breakdown' => $this->getTaxBreakdown(),
        ]);
    }
    
    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $modifier = new static(
            $data['id'] ?? 'tax',
            $data['default_tax_rate'] ?? 13.0,
            $data['category_tax_rates'] ?? [],
            $data['tax_included_in_price'] ?? false
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