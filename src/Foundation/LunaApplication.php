<?php

namespace Dybasedev\LunaPrototype\Foundation;

/**
 * Luna 应用程序主类
 *
 * 这是 Luna 原型框架的核心应用程序类，继承自 LunaModule。
 * 它作为整个框架的入口点，负责协调各个模块的工作。
 *
 * 主要功能：
 * - 管理应用程序的配置
 * - 协调各个模块的初始化
 * - 提供统一的应用程序接口
 * - 处理应用程序级别的业务逻辑
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaApplication extends LunaModule
{
    /**
     * 创建 Luna 应用程序实例
     *
     * @param LunaApplicationConfigure $configure 应用程序配置对象
     */
    public function __construct(
        protected(set) LunaApplicationConfigure $configure,
    )
    {
    }
}