<?php

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