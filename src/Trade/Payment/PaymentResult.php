<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Trade\StatusChangeResult;

/**
 * 支付结果类
 * 
 * 用于封装支付操作的结果，包括成功状态、错误信息、支付单号等
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class PaymentResult extends StatusChangeResult
{
    /**
     * 支付状态
     * 
     * @var PaymentStatus
     */
    protected PaymentStatus $status = PaymentStatus::Pending;
    
    /**
     * 支付单号（第三方支付系统的单号）
     * 
     * @var string|null
     */
    protected ?string $paymentNo = null;
    
    /**
     * 交易号（系统内部的交易号）
     * 
     * @var string|null
     */
    protected ?string $transactionNo = null;
    
    /**
     * 支付金额
     * 
     * @var float|null
     */
    protected ?float $amount = null;
    
    /**
     * 实际支付金额（可能因为优惠等原因与订单金额不同）
     * 
     * @var float|null
     */
    protected ?float $paidAmount = null;
    
    /**
     * 支付时间
     * 
     * @var \DateTimeInterface|null
     */
    protected ?\DateTimeInterface $paidAt = null;
    
    /**
     * 支付方式名称
     * 
     * @var string|null
     */
    protected ?string $paymentMethod = null;
    
    /**
     * 需要跳转的URL（用于第三方支付）
     * 
     * @var string|null
     */
    protected ?string $redirectUrl = null;
    
    /**
     * 额外的支付数据
     * 
     * @var array
     */
    protected array $extraData = [];
    
    /**
     * 创建成功的支付结果
     * 
     * @param array $data
     * @return static
     */
    public static function success(array $data = []): static
    {
        $result = parent::success($data);
        $result->status = PaymentStatus::Success;
        
        if (isset($data['payment_no'])) {
            $result->paymentNo = $data['payment_no'];
        }
        if (isset($data['transaction_no'])) {
            $result->transactionNo = $data['transaction_no'];
        }
        if (isset($data['amount'])) {
            $result->amount = (float)$data['amount'];
        }
        if (isset($data['paid_amount'])) {
            $result->paidAmount = (float)$data['paid_amount'];
        }
        if (isset($data['paid_at'])) {
            $result->paidAt = $data['paid_at'] instanceof \DateTimeInterface ? 
                $data['paid_at'] : new \DateTime($data['paid_at']);
        }
        if (isset($data['payment_method'])) {
            $result->paymentMethod = $data['payment_method'];
        }
        if (isset($data['extra_data'])) {
            $result->extraData = $data['extra_data'];
        }
        
        return $result;
    }
    
    /**
     * 创建待处理的支付结果（需要跳转或等待）
     * 
     * @param string|null $redirectUrl
     * @param array $data
     * @return static
     */
    public static function pending(?string $redirectUrl = null, array $data = []): static
    {
        $result = new static(true, null, $data);
        $result->status = PaymentStatus::Pending;
        $result->redirectUrl = $redirectUrl;
        
        if (isset($data['payment_no'])) {
            $result->paymentNo = $data['payment_no'];
        }
        if (isset($data['transaction_no'])) {
            $result->transactionNo = $data['transaction_no'];
        }
        if (isset($data['amount'])) {
            $result->amount = (float)$data['amount'];
        }
        
        return $result;
    }
    
    /**
     * 创建处理中的支付结果
     * 
     * @param array $data
     * @return static
     */
    public static function processing(array $data = []): static
    {
        $result = new static(true, null, $data);
        $result->status = PaymentStatus::Processing;
        
        if (isset($data['payment_no'])) {
            $result->paymentNo = $data['payment_no'];
        }
        
        return $result;
    }
    
    /**
     * 创建已取消的支付结果
     * 
     * @param string $reason
     * @param array $data
     * @return static
     */
    public static function canceled(string $reason, array $data = []): static
    {
        $result = static::failure($reason, $data);
        $result->status = PaymentStatus::Canceled;
        return $result;
    }
    
    /**
     * 创建退款结果
     * 
     * @param array $data
     * @return static
     */
    public static function refunded(array $data = []): static
    {
        $result = static::success($data);
        $result->status = PaymentStatus::Refunded;
        return $result;
    }
    
    /**
     * 创建部分退款结果
     * 
     * @param array $data
     * @return static
     */
    public static function partialRefunded(array $data = []): static
    {
        $result = static::success($data);
        $result->status = PaymentStatus::PartialRefunded;
        return $result;
    }
    
    /**
     * 设置支付状态
     * 
     * @param PaymentStatus $status
     * @return $this
     */
    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }
    
    /**
     * 获取支付状态
     * 
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }
    
    /**
     * 检查是否为成功状态
     * 
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Success;
    }
    
    /**
     * 检查是否为待处理状态
     * 
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
    
    /**
     * 检查是否为处理中状态
     * 
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === PaymentStatus::Processing;
    }
    
    /**
     * 检查是否需要跳转
     * 
     * @return bool
     */
    public function needsRedirect(): bool
    {
        return !empty($this->redirectUrl);
    }
    
    /**
     * 获取跳转URL
     * 
     * @return string|null
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }
    
    /**
     * 设置支付单号
     * 
     * @param string $paymentNo
     * @return $this
     */
    public function setPaymentNo(string $paymentNo): static
    {
        $this->paymentNo = $paymentNo;
        return $this;
    }
    
    /**
     * 获取支付单号
     * 
     * @return string|null
     */
    public function getPaymentNo(): ?string
    {
        return $this->paymentNo;
    }
    
    /**
     * 设置交易号
     * 
     * @param string $transactionNo
     * @return $this
     */
    public function setTransactionNo(string $transactionNo): static
    {
        $this->transactionNo = $transactionNo;
        return $this;
    }
    
    /**
     * 获取交易号
     * 
     * @return string|null
     */
    public function getTransactionNo(): ?string
    {
        return $this->transactionNo;
    }
    
    /**
     * 设置支付金额
     * 
     * @param float $amount
     * @return $this
     */
    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }
    
    /**
     * 获取支付金额
     * 
     * @return float|null
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }
    
    /**
     * 设置实际支付金额
     * 
     * @param float $paidAmount
     * @return $this
     */
    public function setPaidAmount(float $paidAmount): static
    {
        $this->paidAmount = $paidAmount;
        return $this;
    }
    
    /**
     * 获取实际支付金额
     * 
     * @return float|null
     */
    public function getPaidAmount(): ?float
    {
        return $this->paidAmount;
    }
    
    /**
     * 设置支付时间
     * 
     * @param \DateTimeInterface $paidAt
     * @return $this
     */
    public function setPaidAt(\DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }
    
    /**
     * 获取支付时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }
    
    /**
     * 设置支付方式
     * 
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }
    
    /**
     * 获取支付方式
     * 
     * @return string|null
     */
    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }
    
    /**
     * 设置额外数据
     * 
     * @param array $extraData
     * @return $this
     */
    public function setExtraData(array $extraData): static
    {
        $this->extraData = $extraData;
        return $this;
    }
    
    /**
     * 获取额外数据
     * 
     * @return array
     */
    public function getExtraData(): array
    {
        return $this->extraData;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'status' => $this->status->value,
            'status_name' => $this->status->getDisplayName(),
            'payment_no' => $this->paymentNo,
            'transaction_no' => $this->transactionNo,
            'amount' => $this->amount,
            'paid_amount' => $this->paidAmount,
            'paid_at' => $this->paidAt?->format('Y-m-d H:i:s'),
            'payment_method' => $this->paymentMethod,
            'redirect_url' => $this->redirectUrl,
            'needs_redirect' => $this->needsRedirect(),
            'is_paid' => $this->isPaid(),
            'extra_data' => $this->extraData,
        ]);
    }
}