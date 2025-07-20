<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

/**
 * 标准交易状态枚举
 * 
 * 定义标准交易流程中的所有状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
enum StandardTransactionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case Expired = 'expired';
    
    /**
     * 获取状态名称
     * 
     * @return string
     */
    public function getName(): string
    {
        return match($this) {
            self::PendingPayment => '待支付',
            self::Paid => '已支付',
            self::Completed => '已完成',
            self::Canceled => '已取消',
            self::Expired => '已过期',
        };
    }
    
    /**
     * 获取状态描述
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::PendingPayment => '等待买家付款',
            self::Paid => '买家已付款',
            self::Completed => '交易已完成',
            self::Canceled => '交易已取消',
            self::Expired => '交易已过期',
        };
    }
    
    /**
     * 获取状态码
     * 
     * @return int
     */
    public function getCode(): int
    {
        return short_hash_code($this->value);
    }
    
    /**
     * 是否是最终状态
     * 
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Canceled,
            self::Expired,
        ]);
    }
    
    /**
     * 是否是初始状态
     * 
     * @return bool
     */
    public function isInitial(): bool
    {
        return $this === self::PendingPayment;
    }
    
    /**
     * 获取允许转换到的状态
     * 
     * @return array<self>
     */
    public function getAllowedTransitions(): array
    {
        return match($this) {
            self::PendingPayment => [self::Paid, self::Canceled, self::Expired],
            self::Paid => [self::Completed, self::Canceled],
            self::Completed => [],
            self::Canceled => [],
            self::Expired => [],
        };
    }
    
    /**
     * 检查是否可以转换到指定状态
     * 
     * @param self $toStatus
     * @return bool
     */
    public function canTransitionTo(self $toStatus): bool
    {
        return in_array($toStatus, $this->getAllowedTransitions());
    }
    
    /**
     * 根据状态码获取状态
     * 
     * @param int $code
     * @return self|null
     */
    public static function fromCode(int $code): ?self
    {
        foreach (self::cases() as $status) {
            if ($status->getCode() === $code) {
                return $status;
            }
        }
        
        return null;
    }
    
    /**
     * 根据状态标识获取状态
     * 
     * @param string $key
     * @return self|null
     */
    public static function fromKey(string $key): ?self
    {
        return self::tryFrom($key);
    }
}