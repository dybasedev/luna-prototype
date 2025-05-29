<?php

use Dybasedev\LunaPrototype\AssetsAccount\AccountOperationBuilder;
use Dybasedev\LunaPrototype\AssetsAccount\AccountTransferOperationBuilder;
use Dybasedev\LunaPrototype\AssetsAccount\AccountUpdateOperationBuilder;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;

if (!function_exists('luna_assets_account')) {
    /**
     * @return LunaAssetsAccount
     */
    function luna_assets_account(): LunaAssetsAccount
    {
        return app('luna.assets-account');
    }
}

if (!function_exists('luna_account_update')) {
    function luna_account_update(): AccountUpdateOperationBuilder
    {
        return AccountOperationBuilder::update()->withLunaAssetsAccount(app(LunaAssetsAccount::class));
    }
}

if (!function_exists('luna_account_transfer')) {
    function luna_account_transfer(): AccountTransferOperationBuilder
    {
        return AccountOperationBuilder::transfer()->withLunaAssetsAccount(app(LunaAssetsAccount::class));
    }
}