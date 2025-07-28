<?php

namespace Dybasedev\LunaPrototype\DnW;

/**
 * 交易状态枚举
 */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    
    // 出金特有状态
    case Reviewing = 'reviewing';
    case Rejected = 'rejected';
    
    /**
     * 获取状态代码
     */
    public function getCode(): int
    {
        return short_hash_code($this->value);
    }
    
    /**
     * 获取状态显示名称
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::Pending => '待处理',
            self::Processing => '处理中',
            self::Success => '成功',
            self::Failed => '失败',
            self::Cancelled => '已取消',
            self::Reviewing => '审核中',
            self::Rejected => '已拒绝',
        };
    }
    
    /**
     * 获取状态颜色
     */
    public function getColor(): string
    {
        return match($this) {
            self::Pending => 'gray',
            self::Processing => 'blue',
            self::Success => 'green',
            self::Failed => 'red',
            self::Cancelled => 'gray',
            self::Reviewing => 'yellow',
            self::Rejected => 'red',
        };
    }
    
    /**
     * 从代码获取状态
     */
    public static function fromCode(int $code): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getCode() === $code) {
                return $case;
            }
        }
        return null;
    }
    
    /**
     * 是否为终态
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Cancelled, self::Rejected]);
    }
}