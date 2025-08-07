<?php

use Dybasedev\LunaPrototype\Content\LunaContent;

if (!function_exists('luna_content')) {
    /**
     * 获取 Luna Content 实例
     *
     * @return LunaContent
     */
    function luna_content(): LunaContent
    {
        return app('luna.content');
    }
}