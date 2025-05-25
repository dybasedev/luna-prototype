<?php

namespace Dybasedev\LunaPrototype\Showcase\Structures;

class Field
{
    /**
     * @var string 字段键，用于在前端作为 ID 使用
     */
    protected(set) string $key;

    /**
     * @var string|array 字段名称，用于索引数据
     */
    protected(set) string|array $name;

    /**
     * @var string|null 标题
     */
    protected(set) ?string $title = null;

    /**
     * @var string|null 说明或描述
     */
    protected(set) ?string $description = null;

    /**
     * @var string|null 占位符
     */
    protected(set) ?string $placeholder = null;

    /**
     * @var string 字段类型
     */
    protected(set) string $type = 'text';

    /**
     * @var string|null 提示信息
     */
    protected(set) ?string $tooltip = null;

    /**
     * @var string|int|float|null 宽度
     */
    protected(set) string|int|float|null $width = null;

    /**
     * @var bool 是否隐藏
     */
    protected(set) bool $hidden = false;

    /**
     * @var bool 是否只读
     */
    protected(set) bool $readonly = false;

    /**
     * @var array 透传至前端的属性
     */
    protected(set) array $properties = [];

    /**
     * @var array 透传至表单字段的属性，用于前端存在对字段进行封装时，对包裹的对象进行属性传递
     */
    protected(set) array $formFieldProperties = [];

    /**
     * @var array 扩展设置，对于不同前端实现需求，可进行扩展
     */
    protected(set) array $extendOptions = [];

    /**
     * @var string|null 组件名称，用于针对特定情况选用特定组件
     */
    protected(set) ?string $component = null;

    /**
     * @var string|null 组件名称，用于针对特定情况选用特定组件，这里主要针对存在包裹组件的情形或其他特殊场景
     */
    protected(set) ?string $formFieldComponent = null;

    /**
     * @param array|string $name
     * @param string|null $key
     */
    public function __construct(array|string $name, ?string $key = null)
    {
        if ($key) {
            $this->key = $key;
        } else {
            $this->key = is_array($name) && $name ? implode('-', $name) : $name;
        }

        $this->name($name);
    }

    public function name(array|string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;
        return $this;
    }

    public function width(float|int|string|null $width): static
    {
        $this->width = $width;
        return $this;
    }

    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;
        return $this;
    }

    public function properties(array $properties): static
    {
        $this->properties = $properties;
        return $this;
    }

    public function formFieldProperties(array $formFieldProperties): static
    {
        $this->formFieldProperties = $formFieldProperties;
        return $this;
    }

    public function extendOptions(array $extendOptions): static
    {
        $this->extendOptions = $extendOptions;
        return $this;
    }

    public function component(?string $component): static
    {
        $this->component = $component;
        return $this;
    }

    public function formFieldComponent(?string $formFieldComponent): static
    {
        $this->formFieldComponent = $formFieldComponent;
        return $this;
    }


}