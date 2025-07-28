<?php

namespace Dybasedev\LunaPrototype\Permission\Resources;

/**
 * 简单资源定义
 */
class SimpleResource extends ResourceDefinition
{
    /**
     * 设置支持的操作
     *
     * @param array $actions
     * @return $this
     */
    public function setActions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * 添加操作
     *
     * @param string ...$actions
     * @return $this
     */
    public function addActions(string ...$actions): static
    {
        $this->actions = array_values(array_unique(array_merge($this->actions, $actions)));
        return $this;
    }

    /**
     * 创建 CRUD 资源
     *
     * @param string $name
     * @param string|null $description
     * @return static
     */
    public static function crud(string $name, ?string $description = null): static
    {
        return (new static($name, $description))
            ->setActions(['create', 'read', 'update', 'delete', 'list']);
    }

    /**
     * 创建只读资源
     *
     * @param string $name
     * @param string|null $description
     * @return static
     */
    public static function readOnly(string $name, ?string $description = null): static
    {
        return (new static($name, $description))
            ->setActions(['read', 'list']);
    }

    /**
     * 创建管理资源（所有操作）
     *
     * @param string $name
     * @param string|null $description
     * @return static
     */
    public static function admin(string $name, ?string $description = null): static
    {
        return (new static($name, $description))
            ->setActions(['*']);
    }
}