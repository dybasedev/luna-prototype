<?php

/**
 * Luna 定时任务模块全局辅助函数
 *
 * 这个文件包含了 Luna 定时任务模块中常用的全局辅助函数，
 * 提供了便捷的方式来访问定时任务管理功能。
 *
 * @package Dybasedev\LunaPrototype\Schedule
 * @author Luna Prototype Team
 * @since 1.0.0
 */

use Dybasedev\LunaPrototype\Schedule\LunaSchedule;

if (!function_exists('luna_schedule')) {
    /**
     * 获取 Luna 定时任务管理器实例
     *
     * 这个函数提供了获取 Luna 定时任务管理器对象的便捷方式。
     * 定时任务管理器用于管理和执行各种定时任务。
     *
     * 使用示例：
     * ```php
     * // 获取定时任务管理器
     * $schedule = luna_schedule();
     * 
     * // 获取激活的任务列表
     * $activeTasks = $schedule->getActiveTasks();
     * 
     * // 获取可用的命令列表
     * $commands = $schedule->availableCommands();
     * ```
     *
     * @return LunaSchedule 定时任务管理器实例
     */
    function luna_schedule(): LunaSchedule
    {
        return app('luna.schedule');
    }
}