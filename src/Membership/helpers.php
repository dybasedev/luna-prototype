<?php

use Dybasedev\LunaPrototype\Membership\LunaMembership;

if (!function_exists('luna_membership')) {
    /**
     * 获取 Luna 会员系统管理对象实例
     *
     * @return LunaMembership
     */
    function luna_membership(): LunaMembership
    {
        return app(LunaMembership::class);
    }
}