<?php

namespace Dybasedev\LunaPrototype\DnW;

/**
 * 支付方式定义
 * 
 * 定义了系统支持的各种支付方式标识符
 * 使用泛化的命名，避免与特定现实支付方式绑定
 */
class PaymentMethods
{
    /**
     * 金融机构账户
     * 
     * 传统金融机构的账户，如银行卡、信用卡等
     */
    const FINANCIAL_ACCOUNT = 'financial_account';
    
    /**
     * 数字钱包
     * 
     * 电子钱包服务，如第三方支付平台的账户
     */
    const DIGITAL_WALLET = 'digital_wallet';
    
    /**
     * 区块链地址
     * 
     * 基于区块链技术的地址，如加密货币钱包
     */
    const BLOCKCHAIN_ADDRESS = 'blockchain_address';
    
    /**
     * 预付费卡
     * 
     * 预付费卡、充值卡、礼品卡等
     */
    const PREPAID_CARD = 'prepaid_card';
    
    /**
     * 内部账户
     * 
     * 系统内部的虚拟账户
     */
    const INTERNAL_ACCOUNT = 'internal_account';
    
    /**
     * 其他方式
     */
    const OTHER = 'other';
    
    /**
     * 获取所有支付方式
     */
    public static function all(): array
    {
        return [
            self::FINANCIAL_ACCOUNT,
            self::DIGITAL_WALLET,
            self::BLOCKCHAIN_ADDRESS,
            self::PREPAID_CARD,
            self::INTERNAL_ACCOUNT,
            self::OTHER,
        ];
    }
    
    /**
     * 获取支付方式显示名称
     */
    public static function getDisplayName(string $method): string
    {
        return match($method) {
            self::FINANCIAL_ACCOUNT => '金融机构账户',
            self::DIGITAL_WALLET => '数字钱包',
            self::BLOCKCHAIN_ADDRESS => '区块链地址',
            self::PREPAID_CARD => '预付费卡',
            self::INTERNAL_ACCOUNT => '内部账户',
            self::OTHER => '其他',
            default => $method,
        };
    }
    
    /**
     * 获取支付方式描述
     */
    public static function getDescription(string $method): string
    {
        return match($method) {
            self::FINANCIAL_ACCOUNT => '传统金融机构的账户，如银行卡、信用卡等',
            self::DIGITAL_WALLET => '电子钱包服务，如第三方支付平台',
            self::BLOCKCHAIN_ADDRESS => '基于区块链技术的地址，如加密货币钱包',
            self::PREPAID_CARD => '预付费卡、充值卡、礼品卡等',
            self::INTERNAL_ACCOUNT => '系统内部的虚拟账户',
            self::OTHER => '其他支付方式',
            default => '',
        };
    }
    
    /**
     * 验证支付方式是否有效
     */
    public static function isValid(string $method): bool
    {
        return in_array($method, self::all());
    }
}