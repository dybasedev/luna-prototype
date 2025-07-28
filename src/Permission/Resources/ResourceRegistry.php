<?php

namespace Dybasedev\LunaPrototype\Permission\Resources;

use Illuminate\Support\Str;

/**
 * 资源注册器
 * 
 * 用于注册和管理权限系统中的资源定义
 */
class ResourceRegistry
{
    /**
     * 已注册的资源
     *
     * @var array
     */
    protected array $resources = [];

    /**
     * 资源别名映射
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * 注册资源
     *
     * @param string $name 资源名称
     * @param mixed $definition 资源定义
     * @return void
     */
    public function register(string $name, mixed $definition): void
    {
        if ($definition instanceof ResourceDefinition) {
            $this->resources[$name] = $definition;
        } elseif (is_string($definition)) {
            // 字符串定义，创建简单资源
            $this->resources[$name] = new SimpleResource($name, $definition);
        } elseif (is_array($definition)) {
            // 数组定义，创建资源定义
            $this->resources[$name] = ResourceDefinition::fromArray($name, $definition);
        } elseif (is_callable($definition)) {
            // 回调定义，延迟解析
            $this->resources[$name] = new CallableResource($name, $definition);
        } else {
            throw new \InvalidArgumentException("Invalid resource definition for: {$name}");
        }
    }

    /**
     * 批量注册资源
     *
     * @param array $resources
     * @return void
     */
    public function registerMany(array $resources): void
    {
        foreach ($resources as $name => $definition) {
            $this->register($name, $definition);
        }
    }

    /**
     * 注册资源别名
     *
     * @param string $alias
     * @param string $resource
     * @return void
     */
    public function alias(string $alias, string $resource): void
    {
        $this->aliases[$alias] = $resource;
    }

    /**
     * 获取资源定义
     *
     * @param string $name
     * @return ResourceDefinition|null
     */
    public function get(string $name): ?ResourceDefinition
    {
        // 先检查别名
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        return $this->resources[$name] ?? null;
    }

    /**
     * 检查资源是否已注册
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->resources[$name]) || isset($this->aliases[$name]);
    }

    /**
     * 解析资源标识符
     * 
     * 支持格式：
     * - resource_name
     * - resource_name:action
     * - resource_name:id:action
     * - namespace/resource_name:action
     *
     * @param string $identifier
     * @return array
     */
    public function parseIdentifier(string $identifier): array
    {
        $parts = explode(':', $identifier);
        $resourcePart = array_shift($parts);
        
        // 处理命名空间
        $namespace = null;
        if (str_contains($resourcePart, '/')) {
            [$namespace, $resourcePart] = explode('/', $resourcePart, 2);
        }

        // 剩余部分可能是 id:action 或只是 action
        $id = null;
        $action = null;
        
        if (count($parts) === 2) {
            [$id, $action] = $parts;
        } elseif (count($parts) === 1) {
            $action = $parts[0];
        }

        return [
            'namespace' => $namespace,
            'resource' => $resourcePart,
            'id' => $id,
            'action' => $action,
            'full' => $identifier,
        ];
    }

    /**
     * 构建资源标识符
     *
     * @param string $resource
     * @param string|null $action
     * @param string|null $id
     * @param string|null $namespace
     * @return string
     */
    public function buildIdentifier(
        string $resource,
        ?string $action = null,
        ?string $id = null,
        ?string $namespace = null
    ): string {
        $parts = [];

        if ($namespace) {
            $parts[] = $namespace . '/' . $resource;
        } else {
            $parts[] = $resource;
        }

        if ($id !== null) {
            $parts[] = $id;
        }

        if ($action !== null) {
            $parts[] = $action;
        }

        return implode(':', $parts);
    }

    /**
     * 获取所有已注册的资源
     *
     * @return array
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * 获取资源的所有操作
     *
     * @param string $resource
     * @return array
     */
    public function getResourceActions(string $resource): array
    {
        $definition = $this->get($resource);
        
        if (!$definition) {
            return [];
        }

        return $definition->getActions();
    }

    /**
     * 匹配资源模式
     * 
     * @param string $pattern
     * @return array
     */
    public function match(string $pattern): array
    {
        if (!str_contains($pattern, '*')) {
            return $this->has($pattern) ? [$pattern] : [];
        }

        $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';
        
        return array_keys(array_filter($this->resources, function ($key) use ($regex) {
            return preg_match($regex, $key);
        }, ARRAY_FILTER_USE_KEY));
    }
}