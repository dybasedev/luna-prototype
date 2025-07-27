<?php

namespace Dybasedev\LunaPrototype\Showcase\Attributes;

use Attribute;

/**
 * 列配置注解
 * 
 * 用于配置 DataTable 的列属性
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * 构造函数
     * 
     * @param string $name 列名
     * @param string $title 列标题
     * @param string $type 值类型
     * @param bool $searchable 是否可搜索
     * @param bool $sortable 是否可排序
     * @param bool $hidden 是否隐藏
     * @param int|null $width 宽度
     * @param array $properties 扩展属性
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $type = 'text',
        public bool $searchable = false,
        public bool $sortable = false,
        public bool $hidden = false,
        public ?int $width = null,
        public array $properties = []
    ) {
    }
}