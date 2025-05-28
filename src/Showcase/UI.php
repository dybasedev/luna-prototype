<?php

namespace Dybasedev\LunaPrototype\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;

/**
 * UI 工具类，快速创建组件的工具类
 */
class UI
{
    /**
     * 创建表单字段
     *
     * @param string|array $name
     * @param string|null $key
     * @return Field
     */
    public static function field(string|array $name, ?string $key = null): Field
    {
        return new Field($name, $key);
    }

    /**
     * 创建表单字段组或集合
     *
     * @param string $name
     * @param string|null $key
     * @return FieldGroup
     */
    public static function fieldGroup(string $name, ?string $key = null): FieldGroup
    {
        return new FieldGroup($name, $key);
    }

    /**
     * 创建数据表的列
     *
     * @param string|array $name
     * @param string|null $key
     * @return Column
     */
    public static function column(string|array $name, ?string $key = null): Column
    {
        return new Column($name, $key);
    }
}