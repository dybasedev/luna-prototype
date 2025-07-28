<?php

namespace Dybasedev\LunaPrototype\DnW\Builders;

/**
 * 入金选项构造器
 * 
 * 针对入金交易的特殊选项构造器
 */
class DepositOptionsBuilder extends TransactionOptionsBuilder
{
    /**
     * 设置是否需要人工确认
     */
    public function requiresManualConfirm(bool $requires = true): static
    {
        return $this->addExtraData('requires_manual_confirm', $requires);
    }

    /**
     * 设置自动确认
     */
    public function autoConfirm(bool $auto = true): static
    {
        return $this->addExtraData('auto_confirm', $auto);
    }

    /**
     * 设置支付方式
     */
    public function paymentMethod(string $method): static
    {
        return $this->addExtraData('payment_method', $method);
    }

    /**
     * 设置银行转账信息
     */
    public function bankTransfer(array $bankInfo): static
    {
        return $this->addExtraData('bank_transfer', $bankInfo);
    }

    /**
     * 设置支付凭证信息
     */
    public function paymentProof(array $proofInfo): static
    {
        return $this->addExtraData('payment_proof', $proofInfo);
    }

    /**
     * 设置支付凭证图片
     */
    public function proofImages(array $imageUrls): static
    {
        return $this->addExtraData('proof_images', $imageUrls);
    }

    /**
     * 设置用户备注
     */
    public function userRemark(string $remark): static
    {
        return $this->addExtraData('user_remark', $remark);
    }

    /**
     * 设置通知回调URL
     */
    public function notifyUrl(string $url): static
    {
        return $this->addExtraData('notify_url', $url);
    }

    /**
     * 设置返回URL
     */
    public function returnUrl(string $url): static
    {
        return $this->addExtraData('return_url', $url);
    }

    /**
     * 设置过期时间（分钟）
     */
    public function expiresInMinutes(int $minutes): static
    {
        return $this->addExtraData('expires_in_minutes', $minutes);
    }

    /**
     * 设置过期时间戳
     */
    public function expiresAt(int|\DateTime $timestamp): static
    {
        if ($timestamp instanceof \DateTime) {
            $timestamp = $timestamp->getTimestamp();
        }
        return $this->addExtraData('expires_at', $timestamp);
    }
}