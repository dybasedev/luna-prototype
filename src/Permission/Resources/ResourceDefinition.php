<?php

namespace Dybasedev\LunaPrototype\Permission\Resources;

/**
 * 资源定义基类
 */
abstract class ResourceDefinition
{
    /**
     * 资源名称
     *
     * @var string
     */
    protected(set) string $name;

    /**
     * 资源描述
     *
     * @var string|null
     */
    protected(set) ?string $description = null;

    /**
     * 支持的操作
     *
     * @var array
     */
    protected(set) array $actions = [];

    /**
     * 创建资源定义
     *
     * @param string $name
     * @param string|null $description
     */
    public function __construct(string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->description = $description;
    }


    /**
     * 检查是否支持指定操作
     *
     * @param string $action
     * @return bool
     */
    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions, true) || in_array('*', $this->actions, true);
    }

    /**
     * 获取资源标识符
     *
     * @param string|null $id
     * @param string|null $action
     * @return string
     */
    public function getIdentifier(?string $id = null, ?string $action = null): string
    {
        $parts = [$this->name];
        
        if ($id !== null) {
            $parts[] = $id;
        }
        
        if ($action !== null) {
            $parts[] = $action;
        }
        
        return implode(':', $parts);
    }

    /**
     * 从数组创建资源定义
     *
     * @param string $name
     * @param array $definition
     * @return static
     */
    public static function fromArray(string $name, array $definition): static
    {
        $resource = new SimpleResource($name, $definition['description'] ?? null);
        
        if (isset($definition['actions'])) {
            $resource->setActions($definition['actions']);
        }
        
        return $resource;
    }

    /**
     * 获取资源描述
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * 获取支持的操作
     *
     * @return array
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}