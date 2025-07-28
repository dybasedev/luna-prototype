<?php

namespace Dybasedev\LunaPrototype\Permission\Resources;

/**
 * 可调用资源定义
 * 
 * 支持动态解析资源定义
 */
class CallableResource extends ResourceDefinition
{
    /**
     * 资源解析回调
     *
     * @var callable
     */
    protected $resolver;

    /**
     * 已解析的资源定义
     *
     * @var ResourceDefinition|null
     */
    protected ?ResourceDefinition $resolved = null;

    /**
     * 创建可调用资源
     *
     * @param string $name
     * @param callable $resolver
     */
    public function __construct(string $name, callable $resolver)
    {
        parent::__construct($name);
        $this->resolver = $resolver;
    }

    /**
     * 解析资源定义
     *
     * @return ResourceDefinition
     */
    protected function resolve(): ResourceDefinition
    {
        if ($this->resolved === null) {
            $definition = call_user_func($this->resolver, $this->name);
            
            if ($definition instanceof ResourceDefinition) {
                $this->resolved = $definition;
            } elseif (is_array($definition)) {
                $this->resolved = ResourceDefinition::fromArray($this->name, $definition);
            } else {
                throw new \RuntimeException("Invalid resource definition returned by resolver for: {$this->name}");
            }
        }

        return $this->resolved;
    }

    /**
     * 获取资源描述
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->resolve()->getDescription();
    }

    /**
     * 获取支持的操作
     *
     * @return array
     */
    public function getActions(): array
    {
        return $this->resolve()->getActions();
    }

    /**
     * 检查是否支持指定操作
     *
     * @param string $action
     * @return bool
     */
    public function hasAction(string $action): bool
    {
        return $this->resolve()->hasAction($action);
    }

    /**
     * 资源描述（使用属性钩子）
     *
     * @var string|null
     */
    public string|null $description {
        get => $this->getDescription();
    }

    /**
     * 支持的操作（使用属性钩子）
     *
     * @var array
     */
    public array $actions {
        get => $this->getActions();
    }
}