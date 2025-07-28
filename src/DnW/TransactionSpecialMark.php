<?php

namespace Dybasedev\LunaPrototype\DnW;

/**
 * 交易特殊标记枚举
 * 
 * 用于标记交易的特殊属性，如测试交易、正式交易等
 */
enum TransactionSpecialMark: string
{
    /**
     * 正常交易
     */
    case Normal = 'normal';
    
    /**
     * 测试交易
     */
    case Test = 'test';
    
    /**
     * 开发环境交易
     */
    case Development = 'development';
    
    /**
     * 演示交易
     */
    case Demo = 'demo';

    /**
     * 获取数值代码
     */
    public function getCode(): int
    {
        return match($this) {
            self::Normal => 0,
            self::Test => 1,
            self::Development => 2,
            self::Demo => 3,
        };
    }

    /**
     * 从代码获取枚举实例
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
     * 获取显示名称
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::Normal => '正常',
            self::Test => '测试',
            self::Development => '开发',
            self::Demo => '演示',
        };
    }

    /**
     * 是否为测试类型交易
     */
    public function isTestType(): bool
    {
        return in_array($this, [self::Test, self::Development, self::Demo]);
    }

    /**
     * 获取所有可用的标记
     */
    public static function getAll(): array
    {
        return array_map(function (self $mark) {
            return [
                'value' => $mark->value,
                'code' => $mark->getCode(),
                'display_name' => $mark->getDisplayName(),
                'is_test_type' => $mark->isTestType(),
            ];
        }, self::cases());
    }
}