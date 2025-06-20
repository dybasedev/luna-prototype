<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Support\Facades\Log;

/**
 * 资产账户变更操作构建对象
 */
class AccountUpdateOperationBuilder extends AccountOperationBuilder
{
    public function __construct()
    {
        $this->type = 'update';
    }

    /**
     * 选择需要变更的账户
     *
     * @param int|SessionHolder $owner 账户所属人，若提供整数数据则表示是具体的账户 ID
     * @param string|int|null $account 账户类型，可以提供账户类型名称或经过 hash_code 转换的 ID
     * @return $this
     */
    public function account(int|SessionHolder $owner, string|int|null $account = null): static
    {
        if ($owner instanceof SessionHolder) {
            if (is_null($account)) {
                throw LunaException::create('错误的账户操作');
            }

            $this->operation['account_id'] = $this->luna->ownerAccount($owner, $account)->id;
        } else {
            $this->operation['account_id'] = $owner;
        }

        return $this;
    }

    /**
     * 设置变更余额的类型
     *
     * 如果不存在需要以条件确定变更类型时，更建议直接通过 `available`、`frozen`、`locked` 三个方法来设置变更类型
     *
     * @param AccountBalanceTypeEnum $type
     * @return $this
     */
    public function type(AccountBalanceTypeEnum $type): static
    {
        $this->operation['balance_type'] = $type;
        return $this;
    }

    /**
     * 变更账户可用余额
     *
     * @return $this
     */
    public function available(): static
    {
        return $this->type(AccountBalanceTypeEnum::AvailableBalance);
    }

    /**
     * 变更账户冻结余额
     *
     * @return $this
     */
    public function frozen(): static
    {
        return $this->type(AccountBalanceTypeEnum::FrozenBalance);
    }

    /**
     * 变更账户锁定余额
     *
     * @return $this
     */
    public function locked(): static
    {
        return $this->type(AccountBalanceTypeEnum::LockedBalance);
    }

    /**
     * 增加余额
     *
     * @param string|int|float $amount
     * @return $this
     */
    public function increase(string|int|float $amount): static
    {
        return $this->change(abs($amount));
    }

    /**
     * 减少余额
     *
     * @param string|int|float $amount
     * @return $this
     */
    public function decrease(string|int|float $amount): static
    {
        return $this->change(-abs($amount));
    }

    /**
     * 变更余额
     *
     * @param string|int|float $amount
     * @return $this
     */
    public function change(string|int|float $amount): static
    {
        $this->operation['amount'] = (string)$amount;
        return $this;
    }

    public function build(): array
    {
        if (!array_all(['account_id', 'amount', 'balance_type', 'event_id'], fn($item) => isset($this->operation[$item]))) {
            throw LunaException::create('账户操作参数异常');
        }

        return [
            [
                'account_id' => $this->operation['account_id'],
                'amount' => $this->operation['amount'],
                'balance_type' => $this->operation['balance_type'],
                'payload' => $this->operation['payload'] ?? [],
                'event_id' => $this->operation['event_id'],
            ]
        ];
    }
}