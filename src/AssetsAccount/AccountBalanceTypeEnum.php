<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use RuntimeException;

enum AccountBalanceTypeEnum: int
{
    /**
     * 可用余额
     */
    case AvailableBalance = 1;

    /**
     * 冻结余额
     */
    case FrozenBalance = 2;

    /**
     * 锁定余额
     */
    case LockedBalance = 3;

    /**
     * 获取数据库字段名
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return match ($this) {
            self::AvailableBalance => 'available_balance',
            self::FrozenBalance => 'frozen_balance',
            self::LockedBalance => 'locked_balance',
        };
    }

    /**
     * 转换字段名至枚举
     *
     * @param string $field
     * @return self
     */
    public static function fromFieldName(string $field): self
    {
        return match ($field) {
            'available_balance' => self::AvailableBalance,
            'frozen_balance' => self::FrozenBalance,
            'locked_balance' => self::LockedBalance,
            default => throw new RuntimeException()
        };
    }
}
