<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * 支付方式配置仓库
 * 
 * 提供支付方式特定的配置访问方法
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class PaymentMethodConfigurationRepository extends Repository
{
    /**
     * 获取支付方式名称
     * 
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->get('name');
    }
    
    /**
     * 获取显示名称
     * 
     * @return string|null
     */
    public function getDisplayName(): ?string
    {
        return $this->get('display_name');
    }
    
    /**
     * 获取描述
     * 
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->get('description');
    }
    
    /**
     * 获取图标
     * 
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->get('icon');
    }
    
    /**
     * 是否需要密码
     * 
     * @return bool
     */
    public function requiresPassword(): bool
    {
        return (bool) $this->get('require_password', false);
    }
    
    /**
     * 获取最小金额
     * 
     * @return float
     */
    public function getMinAmount(): float
    {
        return (float) $this->get('min_amount', 0.01);
    }
    
    /**
     * 获取最大金额
     * 
     * @return float
     */
    public function getMaxAmount(): float
    {
        return (float) $this->get('max_amount', 999999.99);
    }
    
    /**
     * 获取折扣率
     * 
     * @return float
     */
    public function getDiscountRate(): float
    {
        return (float) $this->get('discount_rate', 0);
    }
    
    /**
     * 是否测试模式
     * 
     * @return bool
     */
    public function isTestMode(): bool
    {
        return (bool) $this->get('test_mode', false);
    }
    
    /**
     * 获取回调URL
     * 
     * @return string|null
     */
    public function getNotifyUrl(): ?string
    {
        return $this->get('notify_url');
    }
    
    /**
     * 获取返回URL
     * 
     * @return string|null
     */
    public function getReturnUrl(): ?string
    {
        return $this->get('return_url');
    }
    
    /**
     * 获取网关URL
     * 
     * @return string|null
     */
    public function getGatewayUrl(): ?string
    {
        return $this->get('gateway_url');
    }
    
    /**
     * 获取商户ID
     * 
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        return $this->get('merchant_id');
    }
    
    /**
     * 获取API密钥
     * 
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->get('api_key');
    }
    
    /**
     * 获取私钥
     * 
     * @return string|null
     */
    public function getPrivateKey(): ?string
    {
        return $this->get('private_key');
    }
    
    /**
     * 获取公钥
     * 
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->get('public_key');
    }
    
    /**
     * 获取密钥
     * 
     * @return string|null
     */
    public function getSecretKey(): ?string
    {
        return $this->get('secret_key');
    }
    
    /**
     * 获取超时时间（秒）
     * 
     * @return int
     */
    public function getTimeout(): int
    {
        return (int) $this->get('timeout', 300);
    }
    
    /**
     * 获取允许的账户类型
     * 
     * @return array
     */
    public function getAllowedAccountTypes(): array
    {
        return $this->get('allowed_account_types', ['balance']);
    }
    
    /**
     * 获取默认账户类型
     * 
     * @return string
     */
    public function getDefaultAccountType(): string
    {
        return $this->get('account_type', 'balance');
    }
    
    /**
     * 获取事件名称
     * 
     * @param string $type
     * @return string
     */
    public function getEventName(string $type = 'payment'): string
    {
        return match ($type) {
            'payment' => $this->get('event_name', 'trade_payment'),
            'refund' => $this->get('refund_event_name', 'trade_refund'),
            default => $this->get("{$type}_event_name", "trade_{$type}"),
        };
    }
    
    /**
     * 获取密码验证器
     * 
     * @return callable|null
     */
    public function getPasswordVerifier(): ?callable
    {
        $verifier = $this->get('password_verifier');
        return is_callable($verifier) ? $verifier : null;
    }
    
    /**
     * 设置敏感配置项为隐藏
     * 
     * @return static
     */
    public function hideSensitiveData(): static
    {
        return $this->setHidden([
            'api_key',
            'private_key',
            'public_key',
            'secret_key',
            'password',
            'app_secret',
        ]);
    }
}