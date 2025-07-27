<?php

namespace Examples\Trade\AmountModifiers;

use Dybasedev\LunaPrototype\Trade\AmountModifier;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;

/**
 * 优惠券修饰器示例
 * 
 * 展示如何实现优惠券功能，包括验证码、使用次数限制等。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class CouponModifier extends AmountModifier
{
    /**
     * @var string 优惠券代码
     */
    protected string $couponCode;
    
    /**
     * @var float 优惠金额
     */
    protected float $couponAmount;
    
    /**
     * @var string 优惠类型（percentage|fixed）
     */
    protected string $couponType;
    
    /**
     * @var array|null 适用的商品类型
     */
    protected ?array $applicableCategories;
    
    /**
     * 构造函数
     * 
     * @param string $couponCode 优惠券代码
     * @param string $name 优惠券名称
     * @param float $amount 优惠金额或百分比
     * @param string $type 优惠类型
     * @param array|null $categories 适用的商品类型
     */
    public function __construct(
        string $couponCode,
        string $name,
        float $amount,
        string $type = 'fixed',
        ?array $categories = null
    ) {
        parent::__construct('coupon_' . $couponCode, $name, 'coupon');
        
        $this->couponCode = $couponCode;
        $this->couponAmount = $amount;
        $this->couponType = $type;
        $this->applicableCategories = $categories;
        
        // 优惠券通常有较高优先级
        $this->setPriority(50);
    }
    
    /**
     * @inheritDoc
     */
    public function calculate(float $amount, TransactionPreview $preview): float
    {
        $applicableAmount = $this->getApplicableAmount($preview);
        
        if ($applicableAmount <= 0) {
            return $amount;
        }
        
        if ($this->couponType === 'percentage') {
            $discount = $applicableAmount * ($this->couponAmount / 100);
        } else {
            $discount = min($this->couponAmount, $applicableAmount);
        }
        
        // 计算非适用商品的金额
        $nonApplicableAmount = $preview->getBaseAmount() - $applicableAmount;
        
        // 返回折扣后的总金额
        return max(0, $amount - $discount);
    }
    
    /**
     * @inheritDoc
     */
    public function canApply(TransactionPreview $preview): bool
    {
        // 检查是否已过期
        $expiresAt = $this->getMetadata('expires_at');
        if ($expiresAt && now()->isAfter($expiresAt)) {
            return false;
        }
        
        // 检查使用次数限制
        $usageLimit = $this->getMetadata('usage_limit');
        $usedCount = $this->getMetadata('used_count', 0);
        if ($usageLimit !== null && $usedCount >= $usageLimit) {
            return false;
        }
        
        // 检查每用户使用限制
        $userLimit = $this->getMetadata('per_user_limit');
        if ($userLimit !== null) {
            $userUsage = $this->getUserUsageCount($preview->getOwner()->getOperatorId());
            if ($userUsage >= $userLimit) {
                return false;
            }
        }
        
        // 检查最低消费要求
        $minPurchase = $this->getMetadata('min_purchase', 0);
        if ($preview->getBaseAmount() < $minPurchase) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        $desc = $this->getName();
        
        if ($this->couponType === 'percentage') {
            $desc .= sprintf(' - %.0f%% 优惠', $this->couponAmount);
        } else {
            $desc .= sprintf(' - 减免 ¥%.2f', $this->couponAmount);
        }
        
        if ($this->applicableCategories) {
            $desc .= '（部分商品适用）';
        }
        
        return $desc;
    }
    
    /**
     * 获取适用的金额
     * 
     * @param TransactionPreview $preview
     * @return float
     */
    protected function getApplicableAmount(TransactionPreview $preview): float
    {
        if (!$this->applicableCategories) {
            return $preview->getBaseAmount();
        }
        
        $applicableAmount = 0.0;
        
        foreach ($preview->getItems() as $item) {
            $tradable = $item->getTradable();
            
            // 这里假设 Tradable 有 getCategory 方法
            // 实际使用时需要根据具体业务逻辑调整
            if (method_exists($tradable, 'getCategory') && 
                in_array($tradable->getCategory(), $this->applicableCategories)) {
                $applicableAmount += $item->getAmount();
            }
        }
        
        return $applicableAmount;
    }
    
    /**
     * 获取用户使用次数（示例方法，实际应从数据库查询）
     * 
     * @param int|string $userId
     * @return int
     */
    protected function getUserUsageCount(int|string $userId): int
    {
        // 实际实现时应该从数据库查询
        // 这里仅作示例
        return 0;
    }
    
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'coupon_code' => $this->couponCode,
            'coupon_amount' => $this->couponAmount,
            'coupon_type' => $this->couponType,
            'applicable_categories' => $this->applicableCategories,
        ]);
    }
    
    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $modifier = new static(
            $data['coupon_code'],
            $data['name'],
            $data['coupon_amount'],
            $data['coupon_type'] ?? 'fixed',
            $data['applicable_categories'] ?? null
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