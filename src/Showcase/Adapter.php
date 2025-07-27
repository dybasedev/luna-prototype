<?php

namespace Dybasedev\LunaPrototype\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;

/**
 * UI 适配器基类
 * 
 * 负责将 Showcase 的 UI 组件转换为特定前端框架的配置格式
 */
abstract class Adapter
{
    /**
     * 转换列配置
     * 
     * @param Column $column
     * @return array
     */
    abstract public function column(Column $column): array;

    /**
     * 转换字段配置
     * 
     * @param Field|FieldGroup $field
     * @param string|array|null $prefix 字段名前缀
     * @return array
     */
    abstract public function field(Field|FieldGroup $field, string|array|null $prefix = null): array;
}