<?php

namespace Dybasedev\LunaPrototype\Showcase\Structures;

class Column extends Field
{
    /**
     * @var bool 可否排序
     */
    protected(set) bool $sortable = false;

    /**
     * @var bool 可否搜索
     */
    protected(set) bool $searchable = true;

    /**
     * @var bool 可否复制
     */
    protected(set) bool $copyable = false;

    /**
     * @var bool 是否省略
     */
    protected(set) bool $ellipsis = true;

    /**
     * @var int|null 在查询表单中的排序
     */
    protected(set) ?int $order = null;

    /**
     * @var array 透传至搜索字段的属性
     */
    protected(set) array $searchFieldProperties = [];

    /**
     * @var bool 该字段只用于构建搜索表单
     */
    protected(set) bool $onlySearch = false;

    /**
     * @var string|null 列组件，用于针对特定情况选用特定组件，主要用于前端渲染场景
     */
    protected(set) ?string $columnComponent = null;

    public function sortable(bool $sortable = true): Column
    {
        $this->sortable = $sortable;
        return $this;
    }

    public function searchable(bool $searchable = true): Column
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function copyable(bool $copyable = true): Column
    {
        $this->copyable = $copyable;
        return $this;
    }

    public function ellipsis(bool $ellipsis = true): Column
    {
        $this->ellipsis = $ellipsis;
        return $this;
    }

    public function order(?int $order = null): Column
    {
        $this->order = $order;
        return $this;
    }

    public function searchFieldProperties(array $searchFieldProperties): Column
    {
        $this->searchFieldProperties = $searchFieldProperties;
        return $this;
    }

    public function columnComponent(?string $columnComponent): Column
    {
        $this->columnComponent = $columnComponent;
        return $this;
    }

    public function onlySearch(bool $onlySearch = true): static
    {
        $this->onlySearch = $onlySearch;
        return $this;
    }

    /**
     * 快速创建可排序的文本列
     *
     * @param string|array $name 列名称
     * @param string|null $title 标题
     * @return static
     */
    public static function sortableText(string|array $name, ?string $title = null): static
    {
        return static::text($name, $title)->sortable();
    }

    /**
     * 快速创建可搜索的文本列
     *
     * @param string|array $name 列名称
     * @param string|null $title 标题
     * @return static
     */
    public static function searchableText(string|array $name, ?string $title = null): static
    {
        return static::text($name, $title)->searchable();
    }

    /**
     * 快速创建仅用于搜索的字段
     *
     * @param string|array $name 字段名称
     * @param string|null $title 标题
     * @return static
     */
    public static function searchOnly(string|array $name, ?string $title = null): static
    {
        return static::text($name, $title)->onlySearch()->hidden();
    }
}