<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

class AccountTransferOperationBuilder extends AccountOperationBuilder
{
    public function __construct()
    {
        $this->type = 'transfer';
    }

    public function build(): array
    {
        return [];
    }


}