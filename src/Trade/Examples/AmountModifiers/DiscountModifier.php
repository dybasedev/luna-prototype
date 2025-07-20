<?php

namespace Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers;

use Dybasedev\LunaPrototype\Trade\AmountModifier;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;

/**
 * 折扣修饰器示例
 * 
 * 支持百分比折扣和固定金额折扣两种方式。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class DiscountModifier extends AmountModifier
{
    /**
     * @var float 折扣值（百分比或固定金额）
     */
    protected float $discountValue;
    
    /**
     * @var string 折扣类型（percentage|fixed）
     */
    protected string $discountType;
    
    /**
     * @var float|null 最大折扣金额限制
     */
    protected ?float $maxDiscountAmount;
    
    /**
     * 构造函数
     * 
     * @param string $id 唯一标识
     * @param string $name 折扣名称
     * @param float $value 折扣值
     * @param string $type 折扣类型（percentage|fixed）
     * @param float|null $maxAmount 最大折扣金额
     */
    public function __construct(
        string $id,
        string $name,
        float $value,
        string $type = 'percentage',
        ?float $maxAmount = null
    ) {
        parent::__construct($id, $name, 'discount');
        
        if (!in_array($type, ['percentage', 'fixed'])) {
            throw new \InvalidArgumentException('Discount type must be "percentage" or "fixed"');
        }
        
        if ($type === 'percentage' && ($value < 0 || $value > 100)) {
            throw new \InvalidArgumentException('Percentage discount must be between 0 and 100');
        }
        
        if ($value < 0) {
            throw new \InvalidArgumentException('Discount value cannot be negative');
        }
        
        $this->discountValue = $value;
        $this->discountType = $type;
        $this->maxDiscountAmount = $maxAmount;
    }
    
    /**
     * @inheritDoc
     */
    public function calculate(float $amount, TransactionPreview $preview): float
    {
        $discountAmount = 0.0;
        
        if ($this->discountType === 'percentage') {
            $discountAmount = $amount * ($this->discountValue / 100);
            
            // 应用最大折扣限制
            if ($this->maxDiscountAmount !== null && $discountAmount > $this->maxDiscountAmount) {
                $discountAmount = $this->maxDiscountAmount;
            }
        } else {
            // 固定金额折扣
            $discountAmount = min($this->discountValue, $amount);
        }
        
        return max(0, $amount - $discountAmount);
    }
    
    /**
     * @inheritDoc
     */
    public function canApply(TransactionPreview $preview): bool
    {
        // 示例：检查是否满足最低消费要求
        $minAmount = $this->getMetadata('min_amount', 0);
        if ($minAmount > 0 && $preview->getBaseAmount() < $minAmount) {
            return false;
        }
        
        // 示例：检查是否在有效期内
        $validFrom = $this->getMetadata('valid_from');
        $validUntil = $this->getMetadata('valid_until');
        
        if ($validFrom && now()->isBefore($validFrom)) {
            return false;
        }
        
        if ($validUntil && now()->isAfter($validUntil)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        if ($this->discountType === 'percentage') {
            $desc = sprintf('%.0f%% 折扣', $this->discountValue);
            if ($this->maxDiscountAmount !== null) {
                $desc .= sprintf('（最高减免 ¥%.2f）', $this->maxDiscountAmount);
            }
        } else {
            $desc = sprintf('减免 ¥%.2f', $this->discountValue);
        }
        
        return $desc;
    }
    
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'discount_value' => $this->discountValue,
            'discount_type' => $this->discountType,
            'max_discount_amount' => $this->maxDiscountAmount,
        ]);
    }
    
    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $modifier = new static(
            $data['id'],
            $data['name'],
            $data['discount_value'],
            $data['discount_type'] ?? 'percentage',
            $data['max_discount_amount'] ?? null
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