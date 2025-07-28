<?php

namespace Dybasedev\LunaPrototype\DnW\Builders;

/**
 * 出金选项构造器
 * 
 * 针对出金交易的特殊选项构造器
 */
class WithdrawOptionsBuilder extends TransactionOptionsBuilder
{
    /**
     * 设置绑定账户ID
     */
    public function bindingId(int $bindingId): static
    {
        return $this->addExtraData('binding_id', $bindingId);
    }

    /**
     * 设置出金账户信息
     */
    public function withdrawAccount(array $accountInfo): static
    {
        return $this->addExtraData('withdraw_account', $accountInfo);
    }

    /**
     * 设置提现到金融机构账户
     * 
     * @param string $identifier 账户标识符（如卡号、账号等）
     * @param string $holder 账户持有人
     * @param string $institution 金融机构名称
     */
    public function financialAccount(string $identifier, string $holder, string $institution): static
    {
        return $this->withdrawAccount([
            'type' => 'financial',
            'identifier' => $identifier,
            'holder' => $holder,
            'institution' => $institution,
        ]);
    }

    /**
     * 设置提现到电子钱包
     * 
     * @param string $account 钱包账户标识
     * @param string $name 账户名称
     * @param string $provider 钱包服务提供商
     */
    public function digitalWallet(string $account, string $name, string $provider): static
    {
        return $this->withdrawAccount([
            'type' => 'digital_wallet',
            'account' => $account,
            'name' => $name,
            'provider' => $provider,
        ]);
    }

    /**
     * 设置提现到区块链地址
     * 
     * @param string $address 链上地址
     * @param string $network 网络标识
     * @param array $metadata 额外元数据（如代币类型、合约地址等）
     */
    public function blockchainAddress(string $address, string $network, array $metadata = []): static
    {
        return $this->withdrawAccount([
            'type' => 'blockchain',
            'address' => $address,
            'network' => $network,
            'metadata' => $metadata,
        ]);
    }

    /**
     * 设置出金原因
     */
    public function withdrawReason(string $reason): static
    {
        return $this->addExtraData('withdraw_reason', $reason);
    }

    /**
     * 设置是否跳过审核
     */
    public function skipReview(bool $skip = true): static
    {
        return $this->addExtraData('skip_review', $skip);
    }

    /**
     * 设置优先级
     */
    public function priority(string $priority): static
    {
        return $this->addExtraData('priority', $priority);
    }

    /**
     * 设置为高优先级
     */
    public function highPriority(): static
    {
        return $this->priority('high');
    }

    /**
     * 设置为普通优先级
     */
    public function normalPriority(): static
    {
        return $this->priority('normal');
    }

    /**
     * 设置为低优先级
     */
    public function lowPriority(): static
    {
        return $this->priority('low');
    }

    /**
     * 设置通知回调URL
     */
    public function notifyUrl(string $url): static
    {
        return $this->addExtraData('notify_url', $url);
    }

    /**
     * 设置资金密码验证结果
     */
    public function fundPasswordVerified(bool $verified = true): static
    {
        return $this->addExtraData('fund_password_verified', $verified);
    }

    /**
     * 设置二次验证信息
     */
    public function twoFactorAuth(array $authInfo): static
    {
        return $this->addExtraData('two_factor_auth', $authInfo);
    }

    /**
     * 设置风控检查结果
     */
    public function riskControlResult(array $result): static
    {
        return $this->addExtraData('risk_control_result', $result);
    }

    /**
     * 设置预计到账时间
     */
    public function expectedArrivalTime(string $time): static
    {
        return $this->addExtraData('expected_arrival_time', $time);
    }
}