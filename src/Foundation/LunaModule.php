<?php

namespace Dybasedev\LunaPrototype\Foundation;

/**
 * Luna 模块基类，提供模块访问接口
 *
 * 此抽象类作为 Luna 原型框架中所有模块的基类，定义了模块的基本结构和接口。
 * 每个具体的模块都应该继承此类，并实现相应的功能。
 *
 * 模块化设计使得系统具有良好的扩展性和可维护性，每个模块都可以独立开发、
 * 测试和部署。
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class LunaModule
{
    /**
     * 模块基类构造函数
     *
     * 此构造函数为抽象类，不能直接实例化。
     * 子类应该实现自己的构造函数，并接收必要的配置参数。
     */
}