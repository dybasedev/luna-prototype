<?php

use Dybasedev\LunaPrototype\Schedule\LunaSchedule;

if (!function_exists('luna_schedule')) {
    /**
     * 获取LunaSchedule实例
     *
     * @return LunaSchedule
     */
    function luna_schedule(): LunaSchedule
    {
        return app('luna.schedule');
    }
}