<?php

namespace Dybasedev\LunaPrototype\Trade\Payment\Adapters;

use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\Payment\AbstractPaymentMethod;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentResult;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionContext;
use Dybasedev\LunaPrototype\Trade\TransactionPreview;
use Dybasedev\LunaPrototype\Trade\Examples\AmountModifiers\DiscountModifier;

/**
 * 资产账户支付方式适配器
 * 
 * 集成 AssetsAccount 组件，使用账户余额进行支付
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment\Adapters
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class AssetsAccountPaymentMethod extends AbstractPaymentMethod
{
    /**
     * 资产账户管理实例
     * 
     * @var LunaAssetsAccount|null
     */
    protected ?LunaAssetsAccount $assetsAccount = null;
    
    
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->getConfig('name', 'assets_account');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', '账户余额支付');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAvailability(SessionHolder $owner, ?TransactionContext $context = null): array
    {
        try {
            $assetsAccount = $this->getAssetsAccount();
            if (!$assetsAccount) {
                return [
                    'available' => false,
                    'reason' => 'Assets account component not available',
                    'metadata' => []
                ];
            }
            
            // 检查账户是否存在
            $accountType = $this->getAccountType($context);
            $account = $assetsAccount->getAccount($owner, $accountType);
            
            if (!$account) {
                return [
                    'available' => false,
                    'reason' => 'Account not found',
                    'metadata' => ['account_type' => $accountType]
                ];
            }
            
            // 检查账户状态
            if ($account->status !== 1) {
                return [
                    'available' => false,
                    'reason' => 'Account is not active',
                    'metadata' => [
                        'account_type' => $accountType,
                        'account_status' => $account->status
                    ]
                ];
            }
            
            // 获取可用余额
            $availableBalance = $account->available;
            
            return [
                'available' => true,
                'metadata' => [
                    'account_type' => $accountType,
                    'available_balance' => $availableBalance,
                    'frozen_balance' => $account->frozen,
                    'currency' => $account->unit_id ?? 'CNY',
                ]
            ];
            
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'reason' => $e->getMessage(),
                'metadata' => []
            ];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function validateParameters(array $parameters): array
    {
        $errors = [];
        
        // 验证密码（如果配置需要）
        if ($this->config->requiresPassword()) {
            if (empty($parameters['password'])) {
                $errors['password'] = 'Payment password is required';
            }
        }
        
        // 验证账户类型
        if (!empty($parameters['account_type'])) {
            $allowedTypes = $this->config->getAllowedAccountTypes();
            if (!in_array($parameters['account_type'], $allowedTypes)) {
                $errors['account_type'] = 'Invalid account type';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
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
        $result = parent::calculateAmount($amount, $owner, $context);
        
        // 检查是否有余额支付优惠
        $discountRate = $this->config->getDiscountRate();
        if ($discountRate > 0) {
            $discount = $amount * ($discountRate / 100);
            $result['discount'] = round($discount, 2);
            $result['amount'] = round($amount - $discount, 2);
            $result['discount_description'] = sprintf('余额支付优惠 %s%%', $discountRate);
        }
        
        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function supportsPreviewCalculation(): bool
    {
        return $this->config->getDiscountRate() > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function applyToPreview(
        TransactionPreview $preview,
        SessionHolder $owner,
        ?TransactionContext $context = null
    ): array {
        $modifiers = [];
        
        // 如果有折扣，添加折扣修饰器
        $discountRate = $this->config->getDiscountRate();
        if ($discountRate > 0) {
            $modifiers[] = new DiscountModifier(
                'assets_account_discount',
                sprintf('余额支付优惠 %s%%', $discountRate),
                $discountRate,
                'percentage'
            );
        }
        
        return [
            'modifiers' => $modifiers,
            'metadata' => [
                'payment_method' => $this->getName(),
                'discount_rate' => $discountRate,
            ]
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function pay(
        Transaction $transaction,
        array $parameters = [],
        ?TransactionContext $context = null
    ): PaymentResult {
        try {
            $assetsAccount = $this->getAssetsAccount();
            if (!$assetsAccount) {
                return PaymentResult::failure('Assets account component not available');
            }
            
            // 验证参数
            $validation = $this->validateParameters($parameters);
            if (!$validation['valid']) {
                return PaymentResult::failure('Invalid parameters', [
                    'errors' => $validation['errors']
                ]);
            }
            
            // 获取账户类型
            $accountType = $this->getAccountType($context, $parameters);
            
            // 检查余额是否充足
            $owner = $transaction->getOwner();
            $amount = $transaction->getAmount();
            $availability = $this->getAvailability($owner, $context);
            
            if (!$availability['available']) {
                return PaymentResult::failure($availability['reason'] ?? 'Payment method not available');
            }
            
            $availableBalance = $availability['metadata']['available_balance'] ?? 0;
            if ($availableBalance < $amount) {
                return PaymentResult::failure('Insufficient balance', [
                    'required_amount' => $amount,
                    'available_balance' => $availableBalance,
                ]);
            }
            
            // 验证支付密码（如果需要）
            if ($this->config->requiresPassword()) {
                if (!$this->verifyPaymentPassword($owner, $parameters['password'] ?? '')) {
                    return PaymentResult::failure('Invalid payment password');
                }
            }
            
            // 计算实际支付金额（考虑折扣）
            $amountInfo = $this->calculateAmount($amount, $owner, $context);
            $actualAmount = $amountInfo['amount'];
            
            // 执行扣款
            $eventName = $this->config->getEventName('payment');
            $remark = $parameters['remark'] ?? sprintf('交易支付 #%s', $transaction->getTransactionNumber());
            
            $updateResult = luna_account_update()
                ->account($owner, $accountType)
                ->available()
                ->event($eventName)
                ->decrease($actualAmount)
                ->withRemark($remark)
                ->withExtras([
                    'transaction_id' => $transaction->getId(),
                    'transaction_number' => $transaction->getTransactionNumber(),
                    'original_amount' => $amount,
                    'discount' => $amountInfo['discount'] ?? 0,
                ])
                ->submit();
            
            if (!$updateResult) {
                return PaymentResult::failure('Failed to deduct balance');
            }
            
            // 返回成功结果
            return PaymentResult::success([
                'payment_no' => $updateResult->transaction_code,
                'transaction_no' => $transaction->getTransactionNumber(),
                'amount' => $amount,
                'paid_amount' => $actualAmount,
                'paid_at' => now(),
                'payment_method' => $this->getName(),
                'extra_data' => [
                    'account_type' => $accountType,
                    'balance_transaction_id' => $updateResult->id,
                    'balance_transaction_code' => $updateResult->transaction_code,
                    'discount' => $amountInfo['discount'] ?? 0,
                ]
            ]);
            
        } catch (LunaException $e) {
            return PaymentResult::failure($e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return PaymentResult::failure('Payment failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
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
        try {
            $assetsAccount = $this->getAssetsAccount();
            if (!$assetsAccount) {
                return PaymentResult::failure('Assets account component not available');
            }
            
            // 获取原支付信息
            $extraData = $parameters['original_payment_data'] ?? [];
            $accountType = $extraData['account_type'] ?? $this->config->getDefaultAccountType();
            
            // 执行退款（增加余额）
            $owner = $transaction->getOwner();
            $eventName = $this->config->getEventName('refund');
            $remark = sprintf('交易退款 #%s - %s', $transaction->getTransactionNumber(), $reason);
            
            $updateResult = luna_account_update()
                ->account($owner, $accountType)
                ->available()
                ->event($eventName)
                ->increase($amount)
                ->withRemark($remark)
                ->withExtras([
                    'transaction_id' => $transaction->getId(),
                    'transaction_number' => $transaction->getTransactionNumber(),
                    'refund_reason' => $reason,
                    'original_payment_no' => $extraData['balance_transaction_code'] ?? null,
                ])
                ->submit();
            
            if (!$updateResult) {
                return PaymentResult::failure('Failed to refund balance');
            }
            
            // 返回退款结果
            $result = $amount >= $transaction->getAmount() 
                ? PaymentResult::refunded([
                    'refund_amount' => $amount,
                    'refund_no' => $updateResult->transaction_code,
                ])
                : PaymentResult::partialRefunded([
                    'refund_amount' => $amount,
                    'refund_no' => $updateResult->transaction_code,
                ]);
            
            $result->setTransactionNo($transaction->getTransactionNumber());
            $result->setExtraData([
                'account_type' => $accountType,
                'balance_transaction_id' => $updateResult->id,
                'balance_transaction_code' => $updateResult->transaction_code,
                'refund_reason' => $reason,
            ]);
            
            return $result;
            
        } catch (LunaException $e) {
            return PaymentResult::failure($e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return PaymentResult::failure('Refund failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            'supports_partial_payment' => false,
            'supports_refund' => true,
            'supports_query' => false,
            'supports_callback' => false,
            'requires_redirect' => false,
            'instant_payment' => true,  // 立即支付
        ];
    }
    
    /**
     * 获取资产账户管理实例
     * 
     * @return LunaAssetsAccount|null
     */
    protected function getAssetsAccount(): ?LunaAssetsAccount
    {
        if ($this->assetsAccount === null) {
            try {
                $this->assetsAccount = luna_assets_account();
            } catch (\Throwable $e) {
                return null;
            }
        }
        
        return $this->assetsAccount;
    }
    
    /**
     * 设置资产账户管理实例
     * 
     * @param LunaAssetsAccount $assetsAccount
     * @return $this
     */
    public function setAssetsAccount(LunaAssetsAccount $assetsAccount): static
    {
        $this->assetsAccount = $assetsAccount;
        return $this;
    }
    
    /**
     * 获取账户类型
     * 
     * @param TransactionContext|null $context
     * @param array $parameters
     * @return string
     */
    protected function getAccountType(?TransactionContext $context = null, array $parameters = []): string
    {
        // 优先使用参数中的账户类型
        if (!empty($parameters['account_type'])) {
            return $parameters['account_type'];
        }
        
        // 从上下文获取
        if ($context && $context->hasParameter('account_type')) {
            return $context->getParameter('account_type');
        }
        
        // 使用配置的默认账户类型
        return $this->config->getDefaultAccountType();
    }
    
    /**
     * 验证支付密码
     * 
     * @param SessionHolder $owner
     * @param string $password
     * @return bool
     */
    protected function verifyPaymentPassword(SessionHolder $owner, string $password): bool
    {
        // 这里需要根据实际业务实现密码验证逻辑
        // 例如调用用户服务验证支付密码
        // 这里只是示例实现
        
        $verifier = $this->config->getPasswordVerifier();
        if ($verifier !== null) {
            return $verifier($owner, $password);
        }
        
        // 默认实现：总是返回 true（仅用于演示，实际使用时必须实现真正的验证）
        return true;
    }
}