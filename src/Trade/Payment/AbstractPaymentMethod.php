<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionContext;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;

/**
 * 支付方式抽象基类
 * 
 * 提供支付方式的基础实现，简化具体支付方式的开发
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class AbstractPaymentMethod implements PaymentMethod
{
    /**
     * 支付方式配置
     * 
     * @var PaymentMethodConfigurationRepository
     */
    protected PaymentMethodConfigurationRepository $config;
    
    /**
     * 构造函数
     * 
     * @param array|Repository|PaymentMethodConfigurationRepository $config
     */
    public function __construct(array|Repository|PaymentMethodConfigurationRepository $config = [])
    {
        if ($config instanceof PaymentMethodConfigurationRepository) {
            $this->config = $config;
        } elseif ($config instanceof Repository) {
            $this->config = PaymentMethodConfigurationRepository::fromRepository($config);
        } else {
            $this->config = new PaymentMethodConfigurationRepository($config);
        }
        
        // 自动隐藏敏感数据
        $this->config->hideSensitiveData();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return $this->config->get('description');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getIcon(): ?string
    {
        return $this->config->get('icon');
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAvailable(SessionHolder $owner, ?TransactionContext $context = null): bool
    {
        $availability = $this->getAvailability($owner, $context);
        return $availability['available'] ?? false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAvailability(SessionHolder $owner, ?TransactionContext $context = null): array
    {
        return [
            'available' => true,
            'metadata' => []
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function validateParameters(array $parameters): array
    {
        return [
            'valid' => true,
            'errors' => []
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function calculateAmount(
        float $amount,
        SessionHolder $owner,
        ?TransactionContext $context = null
    ): array {
        return [
            'amount' => $amount,
            'original_amount' => $amount,
            'discount' => 0.0,
            'metadata' => []
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function supportsPreviewCalculation(): bool
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function applyToPreview(
        TransactionPreview $preview,
        SessionHolder $owner,
        ?TransactionContext $context = null
    ): array {
        return [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function handleCallback(array $data, ?TransactionContext $context = null): PaymentResult
    {
        return PaymentResult::failure('Callback not supported', [
            'payment_method' => $this->getName()
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function queryStatus(Transaction $transaction, array $parameters = []): PaymentResult
    {
        return PaymentResult::failure('Query not supported', [
            'payment_method' => $this->getName()
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function refund(
        Transaction $transaction,
        float $amount,
        string $reason,
        array $parameters = []
    ): PaymentResult {
        return PaymentResult::failure('Refund not supported', [
            'payment_method' => $this->getName()
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): array
    {
        return $this->config->all();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            'supports_partial_payment' => false,
            'supports_refund' => false,
            'supports_query' => false,
            'supports_callback' => false,
            'requires_redirect' => false,
            'instant_payment' => true,
        ];
    }
    
    /**
     * 获取配置项
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }
    
    /**
     * 设置配置项
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $this->config->set($key, $value);
    }
    
    /**
     * 获取配置仓库
     * 
     * @return PaymentMethodConfigurationRepository
     */
    protected function getConfigRepository(): PaymentMethodConfigurationRepository
    {
        return $this->config;
    }
}