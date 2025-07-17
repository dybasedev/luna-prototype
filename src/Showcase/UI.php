<?php

namespace Dybasedev\LunaPrototype\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;

/**
 * Luna UI 工具类
 *
 * 这是一个用于快速创建 UI 组件的工具类，提供了统一的接口来创建表单字段、
 * 字段组和数据表列等常用的 UI 组件。
 *
 * 主要功能：
 * - 创建表单字段组件
 * - 创建字段组和字段集合
 * - 创建数据表列组件
 * - 提供统一的 UI 组件接口
 *
 * 设计特点：
 * - 静态方法调用，使用便捷
 * - 支持链式调用配置
 * - 与适配器系统集成
 * - 支持多种前端框架
 *
 * 使用示例：
 * ```php
 * // 创建表单字段
 * $nameField = UI::field('name', 'user_name');
 * 
 * // 创建字段组
 * $userGroup = UI::fieldGroup('用户信息');
 * 
 * // 创建数据表列
 * $idColumn = UI::column('ID', 'id');
 * ```
 *
 * @package Dybasedev\LunaPrototype\Showcase
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class UI
{
    /**
     * 创建表单字段
     *
     * 创建一个表单字段对象，用于构建表单界面。
     * 支持各种类型的表单字段，如文本框、选择框、日期选择器等。
     *
     * @param string|array $name 字段名称或国际化名称数组
     * @param string|null $key 字段键名，如果为 null 则使用 name 作为键名
     * @return Field 表单字段对象
     */
    public static function field(string|array $name, ?string $key = null): Field
    {
        return new Field($name, $key);
    }

    /**
     * 创建表单字段组或集合
     *
     * 创建一个字段组对象，用于将相关的表单字段组织在一起。
     * 字段组可以包含多个字段，并提供统一的布局和样式。
     *
     * @param string $name 字段组名称
     * @param string|null $key 字段组键名，如果为 null 则使用 name 作为键名
     * @return FieldGroup 字段组对象
     */
    public static function fieldGroup(string $name, ?string $key = null): FieldGroup
    {
        return new FieldGroup($name, $key);
    }

    /**
     * 创建数据表的列
     *
     * 创建一个数据表列对象，用于构建数据表界面。
     * 支持各种类型的列，如文本列、数字列、日期列、操作列等。
     *
     * @param string|array $name 列名称或国际化名称数组
     * @param string|null $key 列键名，如果为 null 则使用 name 作为键名
     * @return Column 数据表列对象
     */
    public static function column(string|array $name, ?string $key = null): Column
    {
        return new Column($name, $key);
    }
}