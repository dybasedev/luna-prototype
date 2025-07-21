<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

/**
 * 持有状态枚举
 * 
 * 定义持有对象的各种状态
 * 
 * @package Dybasedev\LunaPrototype\HoldingObject
 */
enum HoldingStatus: int
{
    /**
     * 正常
     */
    case Normal = 1;

    /**
     * 冻结
     */
    case Frozen = 2;

    /**
     * 已使用
     */
    case Used = 3;

    /**
     * 已过期
     */
    case Expired = 4;

    /**
     * 已取消
     */
    case Cancelled = 5;

    /**
     * 失效
     */
    case Invalid = 6;

    /**
     * 禁用
     */
    case Disabled = 7;

    /**
     * 获取状态显示名称
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::Normal => '正常',
            self::Frozen => '冻结',
            self::Used => '已使用',
            self::Expired => '已过期',
            self::Cancelled => '已取消',
            self::Invalid => '失效',
            self::Disabled => '禁用',
        };
    }

    /**
     * 是否为可用状态
     * 
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this === self::Normal;
    }

    /**
     * 是否为最终状态
     * 
     * @return bool
     */
    public function isFinal(): bool
    {
        return match($this) {
            self::Normal, self::Frozen => false,
            default => true,
        };
    }

    /**
     * 是否为不可用状态
     * 
     * @return bool
     */
    public function isUnavailable(): bool
    {
        return !$this->isAvailable();
    }

    /**
     * 是否为正常状态
     * 
     * @return bool
     */
    public function isNormal(): bool
    {
        return $this === self::Normal;
    }

    /**
     * 是否为冻结状态
     * 
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this === self::Frozen;
    }

    /**
     * 是否为失效状态
     * 
     * @return bool
     */
    public function isInvalid(): bool
    {
        return $this === self::Invalid;
    }

    /**
     * 是否为禁用状态
     * 
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this === self::Disabled;
    }

    /**
     * 是否为活跃状态（正常或冻结）
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Normal, self::Frozen], true);
    }

    /**
     * 从整数值创建枚举
     * 
     * @param int $value
     * @return self|null
     */
    public static function tryFromInt(int $value): ?self
    {
        return match($value) {
            1 => self::Normal,
            2 => self::Frozen,
            3 => self::Used,
            4 => self::Expired,
            5 => self::Cancelled,
            6 => self::Invalid,
            7 => self::Disabled,
            default => null,
        };
    }
}