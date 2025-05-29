<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Support\Facades\Log;

class AccountUpdateOperationBuilder extends AccountOperationBuilder
{
    public function __construct()
    {
        $this->type = 'update';
    }

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

    public function type(AccountBalanceTypeEnum $type): static
    {
        $this->operation['balance_type'] = $type;
        return $this;
    }

    public function available(): static
    {
        return $this->type(AccountBalanceTypeEnum::AvailableBalance);
    }

    public function frozen(): static
    {
        return $this->type(AccountBalanceTypeEnum::FrozenBalance);
    }

    public function locked(): static
    {
        return $this->type(AccountBalanceTypeEnum::LockedBalance);
    }

    public function increase(string|int|float $amount): static
    {
        return $this->change(abs($amount));
    }

    public function decrease(string|int|float $amount): static
    {
        return $this->change(-abs($amount));
    }

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