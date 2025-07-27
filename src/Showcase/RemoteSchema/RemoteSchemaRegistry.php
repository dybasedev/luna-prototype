<?php

namespace Dybasedev\LunaPrototype\Showcase\RemoteSchema;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Support\Str;

/**
 * RemoteSchema 注册器
 * 
 * 负责管理和注册 RemoteSchema 实例
 */
class RemoteSchemaRegistry
{
    /**
     * 已注册的 RemoteSchema
     * 
     * @var array<string, array{class: class-string|callable, meta: array}>
     */
    protected array $schemas = [];

    /**
     * RemoteSchema 实例缓存
     * 
     * @var array<string, RemoteSchemaInterface>
     */
    protected array $instances = [];

    /**
     * 注册 RemoteSchema
     * 
     * @param string $key 唯一标识
     * @param class-string|callable $schema RemoteSchema 类名或工厂函数
     * @param array $meta 元数据
     * @return void
     */
    public function register(string $key, string|callable $schema, array $meta = []): void
    {
        if (isset($this->schemas[$key])) {
            throw LunaException::create("RemoteSchema '{$key}' already registered")
                ->withDisplayMessage("RemoteSchema 已注册");
        }

        // 如果是类名，验证类是否存在并实现了接口
        if (is_string($schema)) {
            if (!class_exists($schema)) {
                throw LunaException::create("RemoteSchema class '{$schema}' not found")
                    ->withDisplayMessage("RemoteSchema 类不存在");
            }

            $reflection = new \ReflectionClass($schema);
            if (!$reflection->implementsInterface(RemoteSchemaInterface::class) && 
                !$reflection->isSubclassOf(RemoteSchema::class)) {
                throw LunaException::create("Class '{$schema}' must implement RemoteSchemaInterface or extend RemoteSchema")
                    ->withDisplayMessage("类必须实现 RemoteSchemaInterface 接口或继承 RemoteSchema");
            }
        }

        // 如果是类名且没有提供元数据，尝试从属性/注解读取
        $defaultMeta = [
            'title' => $this->generateTitle($key),
            'description' => null,
            'group' => 'default',
            'visible' => true,
            'sortOrder' => 0,
        ];
        
        if (is_string($schema) && empty($meta)) {
            $generatedMeta = $this->generateMeta($schema);
            $defaultMeta = array_merge($defaultMeta, $generatedMeta);
        }
        
        $this->schemas[$key] = [
            'class' => $schema,
            'meta' => array_merge($defaultMeta, $meta),
        ];
    }

    /**
     * 生成元数据
     * 
     * @param string $className
     * @return array
     */
    protected function generateMeta(string $className): array
    {
        $reflection = new \ReflectionClass($className);
        
        $meta = [];

        // 使用 PHP 8 Attributes
        $attributes = $reflection->getAttributes(\Dybasedev\LunaPrototype\Showcase\Attributes\RemoteSchemaMeta::class);
        if (!empty($attributes)) {
            $attribute = $attributes[0]->newInstance();
            $meta['title'] = $attribute->title;
            $meta['description'] = $attribute->description;
            $meta['group'] = $attribute->group;
            $meta['sortOrder'] = $attribute->sortOrder;
            $meta['visible'] = $attribute->visible;
        }

        return $meta;
    }

    /**
     * 生成标题
     * 
     * @param string $key
     * @return string
     */
    protected function generateTitle(string $key): string
    {
        return Str::title(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * 获取 RemoteSchema 实例
     * 
     * @param string $key
     * @return RemoteSchemaInterface
     * @throws LunaException
     */
    public function get(string $key): RemoteSchemaInterface
    {
        if (!isset($this->schemas[$key])) {
            throw LunaException::create("RemoteSchema '{$key}' not found")
                ->withDisplayMessage("RemoteSchema 不存在");
        }

        if (!isset($this->instances[$key])) {
            $config = $this->schemas[$key];
            $schema = $config['class'];

            if (is_callable($schema)) {
                $instance = call_user_func($schema);
            } else {
                $instance = app($schema);
            }

            if (!$instance instanceof RemoteSchemaInterface) {
                throw LunaException::create("RemoteSchema '{$key}' must return an instance of RemoteSchemaInterface")
                    ->withDisplayMessage("RemoteSchema 必须返回 RemoteSchemaInterface 实例");
            }

            $this->instances[$key] = $instance;
        }

        return $this->instances[$key];
    }

    /**
     * 检查 RemoteSchema 是否存在
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->schemas[$key]);
    }

    /**
     * 获取所有已注册的 RemoteSchema 键
     * 
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * 获取所有 RemoteSchema 的元数据
     * 
     * @param string|null $group 过滤分组
     * @return array<string, array>
     */
    public function all(?string $group = null): array
    {
        $result = [];

        foreach ($this->schemas as $key => $config) {
            $meta = $config['meta'];
            
            // 过滤不可见的
            if (!($meta['visible'] ?? true)) {
                continue;
            }

            // 过滤分组
            if ($group !== null && ($meta['group'] ?? 'default') !== $group) {
                continue;
            }

            $result[$key] = array_merge(['key' => $key], $meta);
        }

        // 按 sortOrder 排序
        uasort($result, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $result;
    }

    /**
     * 获取分组列表
     * 
     * @return array<string>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->schemas as $config) {
            $group = $config['meta']['group'] ?? 'default';
            if (!in_array($group, $groups)) {
                $groups[] = $group;
            }
        }

        sort($groups);
        return $groups;
    }

    /**
     * 构建注册器
     * 
     * @return void
     */
    public function build(): void
    {
        // 清理实例缓存
        $this->instances = [];
    }
}