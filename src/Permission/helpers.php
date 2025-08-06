<?php

use Dybasedev\LunaPrototype\Permission\LunaPermission;

if (!function_exists('luna_permission')) {
    /**
     * 获取 Luna 权限管理器实例
     *
     * @return LunaPermission
     */
    function luna_permission(): LunaPermission
    {
        return app('luna.permission');
    }
}