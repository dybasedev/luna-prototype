<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

abstract class AccountOperationBuilder
{
    protected(set) array $operation = [];

    protected(set) string $type;

    protected(set) ?LunaAssetsAccount $luna = null;

    public static function update(): AccountUpdateOperationBuilder
    {
        return new AccountUpdateOperationBuilder();
    }

    public static function transfer(): AccountTransferOperationBuilder
    {
        return new AccountTransferOperationBuilder();
    }

    public function withLunaAssetsAccount(LunaAssetsAccount $account): static
    {
        $this->luna = $account;
        return $this;
    }

    public function payload(?array $payload = null): static
    {
        if ($payload) {
            $this->operation['payload'] = $payload;
        }

        return $this;
    }

    public function event(int|string $event): static
    {
        $this->operation['event_id'] = is_string($event) ? hash_code($event) : $event;
        return $this;
    }

    abstract public function build(): array;
}