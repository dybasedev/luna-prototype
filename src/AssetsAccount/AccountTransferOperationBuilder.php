<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 资产账户转账操作构建对象
 */
class AccountTransferOperationBuilder extends AccountOperationBuilder
{
    public function __construct()
    {
        $this->type = 'transfer';
    }

    private function account(string $direction, int|SessionHolder $owner, string|int|null $account = null): static
    {
        if ($owner instanceof SessionHolder) {
            if (is_null($account)) {
                throw LunaException::create('错误的账户操作');
            }

            $this->operation[$direction]['account_id'] = $this->luna->ownerAccount($owner, $account)->id;
        } else {
            $this->operation[$direction]['account_id'] = $owner;
        }

        return $this;
    }

    private function type(string $direction, AccountBalanceTypeEnum $type): static
    {
        $this->operation[$direction]['balance_type'] = $type;
        return $this;
    }

    /**
     * 选择需要从哪一账户转出
     *
     * @param int|SessionHolder $owner 账户所属人，若提供整数数据则表示是具体的账户 ID
     * @param string|int|null $account 账户类型，可以提供账户类型名称或经过 hash_code 转换的 ID
     * @return $this
     */
    public function from(int|SessionHolder $owner, string|int|null $account = null): static
    {
        return $this->account('from', $owner, $account);
    }

    /**
     * 选择需要转入哪一账户
     *
     * @param int|SessionHolder $owner 账户所属人，若提供整数数据则表示是具体的账户 ID
     * @param string|int|null $account 账户类型，可以提供账户类型名称或经过 hash_code 转换的 ID
     * @return $this
     */
    public function to(int|SessionHolder $owner, string|int|null $account = null): static
    {
        return $this->account('to', $owner, $account);
    }

    /**
     * 设置转出余额的类型
     *
     * 如果不存在需要以条件确定变更类型时，更建议直接通过 `fromAvailable`、`fromFrozen`、`fromLocked` 三个方法来设置变更类型
     *
     * @param AccountBalanceTypeEnum $type
     * @return $this
     */
    public function fromType(AccountBalanceTypeEnum $type): static
    {
        return $this->type('from', $type);
    }

    /**
     * 设置转入余额的类型
     *
     * 如果不存在需要以条件确定变更类型时，更建议直接通过 `toAvailable`、`toFrozen`、`toLocked` 三个方法来设置变更类型
     *
     * @param AccountBalanceTypeEnum $type
     * @return $this
     */
    public function toType(AccountBalanceTypeEnum $type): static
    {
        return $this->type('to', $type);
    }

    /**
     * 设置转出余额为可用余额
     *
     * @return $this
     */
    public function fromAvailable(): static
    {
        return $this->fromType(AccountBalanceTypeEnum::AvailableBalance);
    }

    /**
     * 设置转出余额为冻结余额
     *
     * @return $this
     */
    public function fromFrozen(): static
    {
        return $this->fromType(AccountBalanceTypeEnum::FrozenBalance);
    }

    /**
     * 设置转出余额为锁定余额
     *
     * @return $this
     */
    public function fromLocked(): static
    {
        return $this->fromType(AccountBalanceTypeEnum::LockedBalance);
    }

    /**
     * 设置转入余额为可用余额
     *
     * @return $this
     */
    public function toAvailable(): static
    {
        return $this->toType(AccountBalanceTypeEnum::AvailableBalance);
    }

    /**
     * 设置转入余额为冻结余额
     *
     * @return $this
     */
    public function toFrozen(): static
    {
        return $this->toType(AccountBalanceTypeEnum::FrozenBalance);
    }

    /**
     * 设置转入余额为锁定余额
     *
     * @return $this
     */
    public function toLocked(): static
    {
        return $this->toType(AccountBalanceTypeEnum::LockedBalance);
    }

    /**
     * 设置转账金额
     *
     * @param string|int|float $amount
     * @return $this
     */
    public function amount(string|int|float $amount): static
    {
        $this->operation['amount'] = (string)$amount;
        return $this;
    }

    public function build(): array
    {
        // 检查参数是否齐备
        if (!array_all(['account_id', 'balance_type'], fn($item) => isset($this->operation['from'][$item]) && isset($this->operation['to'][$item]))) {
            throw LunaException::create('转账操作参数异常，未设置转出或转入账户');
        }

        if (!array_all(['amount', 'event_id'], fn($item) => isset($this->operation[$item]))) {
            throw LunaException::create('账户操作参数异常');
        }

        // 检查是否是同账户同余额类型的转账
        if ($this->operation['from']['account_id'] === $this->operation['to']['account_id'] && $this->operation['from']['balance_type'] === $this->operation['to']['balance_type']) {
            // 返回空操作
            return [];
        }

        return [
            [
                'account_id' => $this->operation['from']['account_id'],
                'amount' => $this->operation['amount'],
                'balance_type' => $this->operation['from']['balance_type'],
                'payload' => $this->operation['payload'] ?? [],
                'event_id' => $this->operation['event_id'],
            ],
            [
                'account_id' => $this->operation['to']['account_id'],
                'amount' => $this->operation['amount'],
                'balance_type' => $this->operation['to']['balance_type'],
                'payload' => $this->operation['payload'] ?? [],
                'event_id' => $this->operation['event_id'],
            ],
        ];
    }


}