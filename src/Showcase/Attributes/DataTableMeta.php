<?php

namespace Dybasedev\LunaPrototype\Showcase\Attributes;

use Attribute;

/**
 * DataTable 元数据注解
 * 
 * 用于标注 DataTable 类的元数据信息
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DataTableMeta
{
    /**
     * 构造函数
     * 
     * @param string $title 标题
     * @param string $description 描述
     * @param string $group 分组
     * @param int $sortOrder 排序顺序
     * @param bool $visible 是否可见
     */
    public function __construct(
        public string $title,
        public string $description = '',
        public string $group = 'default',
        public int $sortOrder = 0,
        public bool $visible = true
    ) {
    }
}