<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

/**
 * 支付状态枚举
 * 
 * 定义支付的各种状态
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
enum PaymentStatus: string
{
    /**
     * 待支付
     */
    case Pending = 'pending';
    
    /**
     * 处理中
     */
    case Processing = 'processing';
    
    /**
     * 支付成功
     */
    case Success = 'success';
    
    /**
     * 支付失败
     */
    case Failed = 'failed';
    
    /**
     * 已取消
     */
    case Canceled = 'canceled';
    
    /**
     * 已退款
     */
    case Refunded = 'refunded';
    
    /**
     * 部分退款
     */
    case PartialRefunded = 'partial_refunded';
    
    /**
     * 获取状态显示名称
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::Pending => '待支付',
            self::Processing => '处理中',
            self::Success => '支付成功',
            self::Failed => '支付失败',
            self::Canceled => '已取消',
            self::Refunded => '已退款',
            self::PartialRefunded => '部分退款',
        };
    }
    
    /**
     * 获取状态颜色
     * 
     * @return string
     */
    public function getColor(): string
    {
        return match($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
            self::Canceled => 'secondary',
            self::Refunded => 'primary',
            self::PartialRefunded => 'primary',
        };
    }
    
    /**
     * 是否为最终状态
     * 
     * @return bool
     */
    public function isFinal(): bool
    {
        return match($this) {
            self::Pending, self::Processing => false,
            default => true,
        };
    }
    
    /**
     * 是否为成功状态
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this === self::Success;
    }
    
    /**
     * 是否为退款状态
     * 
     * @return bool
     */
    public function isRefunded(): bool
    {
        return in_array($this, [self::Refunded, self::PartialRefunded]);
    }
}