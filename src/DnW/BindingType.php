<?php

namespace Dybasedev\LunaPrototype\DnW;

/**
 * 绑定类型枚举
 * 
 * 定义账户绑定的类型，使用泛化命名
 */
enum BindingType: string
{
    /**
     * 金融账户
     * 现实中对应：银行卡、信用卡等传统金融机构账户
     */
    case FinancialAccount = 'financial_account';
    
    /**
     * 数字钱包
     * 现实中对应：支付宝、微信支付、PayPal等电子钱包
     */
    case DigitalWallet = 'digital_wallet';
    
    /**
     * 区块链地址
     * 现实中对应：比特币、以太坊等加密货币地址
     */
    case BlockchainAddress = 'blockchain_address';
    
    /**
     * 预付费账户
     * 现实中对应：充值卡、礼品卡、预付费卡等
     */
    case PrepaidAccount = 'prepaid_account';
    
    /**
     * 内部账户
     * 现实中对应：系统内部虚拟账户、余额账户等
     */
    case InternalAccount = 'internal_account';
    
    /**
     * 其他类型
     */
    case Other = 'other';
    
    /**
     * 获取类型代码
     */
    public function getCode(): int
    {
        return short_hash_code($this->value);
    }
    
    /**
     * 获取类型显示名称
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::FinancialAccount => '金融账户',
            self::DigitalWallet => '数字钱包',
            self::BlockchainAddress => '区块链地址',
            self::PrepaidAccount => '预付费账户',
            self::InternalAccount => '内部账户',
            self::Other => '其他',
        };
    }
    
    /**
     * 从代码获取类型
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
     * 获取类型描述
     */
    public function getDescription(): string
    {
        return match($this) {
            self::FinancialAccount => '传统金融机构的账户',
            self::DigitalWallet => '电子支付服务提供商的账户',
            self::BlockchainAddress => '基于区块链技术的地址',
            self::PrepaidAccount => '预先充值的账户或卡片',
            self::InternalAccount => '系统内部管理的虚拟账户',
            self::Other => '其他类型的账户',
        };
    }
}