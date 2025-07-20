<?php

namespace Dybasedev\LunaPrototype\Trade\Payment\Examples;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\Payment\AbstractPaymentMethod;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentResult;
use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionContext;

/**
 * 模拟第三方支付方式示例
 * 
 * 这是一个示例实现，展示如何创建需要跳转到第三方的支付方式
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment\Examples
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class MockThirdPartyPayment extends AbstractPaymentMethod
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->getConfig('name', 'mock_payment');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', '模拟支付');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAvailability(SessionHolder $owner, ?TransactionContext $context = null): array
    {
        // 模拟一些可用性检查
        $minAmount = $this->config->getMinAmount();
        $maxAmount = $this->config->getMaxAmount();
        
        return [
            'available' => true,
            'metadata' => [
                'min_amount' => $minAmount,
                'max_amount' => $maxAmount,
                'supported_currencies' => ['CNY', 'USD'],
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
            // 验证金额范围
            $amount = $transaction->getAmount();
            $minAmount = $this->config->getMinAmount();
            $maxAmount = $this->config->getMaxAmount();
            
            if ($amount < $minAmount || $amount > $maxAmount) {
                return PaymentResult::failure(sprintf(
                    'Amount must be between %s and %s',
                    $minAmount,
                    $maxAmount
                ));
            }
            
            // 生成模拟的支付单号
            $paymentNo = $this->generatePaymentNo();
            
            // 构建支付URL（实际应用中这里会调用第三方API）
            $gatewayUrl = $this->config->getGatewayUrl() ?? 'https://mock-payment.example.com/pay';
            $notifyUrl = $this->config->getNotifyUrl() ?? '/payment/mock/notify';
            $returnUrl = $this->config->getReturnUrl() ?? '/payment/mock/return';
            
            $paymentUrl = $gatewayUrl . '?' . http_build_query([
                'merchant_id' => $this->config->getMerchantId() ?? 'MOCK_MERCHANT',
                'payment_no' => $paymentNo,
                'transaction_no' => $transaction->getTransactionNumber(),
                'amount' => $amount,
                'currency' => 'CNY',
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'sign' => $this->generateSign([
                    'payment_no' => $paymentNo,
                    'amount' => $amount,
                ]),
            ]);
            
            // 返回待支付结果
            return PaymentResult::pending($paymentUrl, [
                'payment_no' => $paymentNo,
                'transaction_no' => $transaction->getTransactionNumber(),
                'amount' => $amount,
            ]);
            
        } catch (\Throwable $e) {
            return PaymentResult::failure('Payment initialization failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function handleCallback(array $data, ?TransactionContext $context = null): PaymentResult
    {
        // 验证签名
        if (!$this->verifyCallbackSign($data)) {
            return PaymentResult::failure('Invalid signature');
        }
        
        // 检查支付状态
        $status = $data['status'] ?? '';
        
        return match ($status) {
            'SUCCESS' => PaymentResult::success([
                'payment_no' => $data['payment_no'],
                'transaction_no' => $data['transaction_no'],
                'amount' => (float)$data['amount'],
                'paid_amount' => (float)$data['paid_amount'],
                'paid_at' => $data['paid_at'] ?? now(),
                'payment_method' => $this->getName(),
            ]),
            
            'PROCESSING' => PaymentResult::processing([
                'payment_no' => $data['payment_no'],
            ]),
            
            'FAILED' => PaymentResult::failure($data['error_msg'] ?? 'Payment failed'),
            
            default => PaymentResult::failure('Unknown payment status: ' . $status),
        };
    }
    
    /**
     * {@inheritdoc}
     */
    public function queryStatus(Transaction $transaction, array $parameters = []): PaymentResult
    {
        // 模拟查询支付状态
        $paymentNo = $parameters['payment_no'] ?? null;
        
        if (!$paymentNo) {
            return PaymentResult::failure('Payment number is required');
        }
        
        // 实际应用中这里会调用第三方API查询
        // 这里只是模拟返回
        return PaymentResult::success([
            'payment_no' => $paymentNo,
            'transaction_no' => $transaction->getTransactionNumber(),
            'amount' => $transaction->getAmount(),
            'paid_amount' => $transaction->getAmount(),
            'paid_at' => now(),
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
        // 验证退款金额
        if ($amount > $transaction->getAmount()) {
            return PaymentResult::failure('Refund amount exceeds transaction amount');
        }
        
        // 生成退款单号
        $refundNo = 'REFUND_' . $this->generatePaymentNo();
        
        // 模拟退款API调用
        // 实际应用中这里会调用第三方API
        
        // 模拟退款成功
        if ($amount >= $transaction->getAmount()) {
            return PaymentResult::refunded([
                'refund_no' => $refundNo,
                'refund_amount' => $amount,
                'transaction_no' => $transaction->getTransactionNumber(),
            ]);
        } else {
            return PaymentResult::partialRefunded([
                'refund_no' => $refundNo,
                'refund_amount' => $amount,
                'transaction_no' => $transaction->getTransactionNumber(),
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
            'supports_query' => true,
            'supports_callback' => true,
            'requires_redirect' => true,  // 需要跳转
            'instant_payment' => false,   // 非即时支付
        ];
    }
    
    /**
     * 生成支付单号
     * 
     * @return string
     */
    protected function generatePaymentNo(): string
    {
        return strtoupper(uniqid('PAY'));
    }
    
    /**
     * 生成签名
     * 
     * @param array $data
     * @return string
     */
    protected function generateSign(array $data): string
    {
        ksort($data);
        $signStr = http_build_query($data) . '&key=' . ($this->config->getSecretKey() ?? 'MOCK_SECRET');
        return md5($signStr);
    }
    
    /**
     * 验证回调签名
     * 
     * @param array $data
     * @return bool
     */
    protected function verifyCallbackSign(array $data): bool
    {
        $sign = $data['sign'] ?? '';
        unset($data['sign']);
        
        return $sign === $this->generateSign($data);
    }
}