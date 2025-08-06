<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 处理器管理类
 * 
 * 负责管理和维护所有注册的处理器，包括：
 * - 处理器组的管理
 * - 处理器实体的创建和查询
 * - 处理器实例的创建和配置
 * - 缓存管理
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Handler
 */
class LunaHandler
{
    public function __construct(
        protected LunaHandlerConfigure $configure,
        protected Cache $cache
    ) {

    }

    /**
     * 获取所有处理器组
     * 
     * 返回系统中注册的所有处理器组信息。
     * 每个组包含 ID、名称和显示名称。
     * 
     * @return array 处理器组数组
     */
    public function groups(): array
    {
        return array_map(fn($id, $group) => [
            'id' => $id,
            'name' => $group['name'],
            'display_name' => $group['display_name'] ?? $group['name'],
        ], array_keys($this->configure->groups), $this->configure->groups);
    }

    /**
     * 获取处理器列表
     * 
     * 返回指定组或所有组的处理器信息。
     * 如果不指定组，则返回所有注册的处理器。
     * 
     * @param string|int|null $group 处理器组名称或ID，null表示所有组
     * @return array 处理器信息数组
     * @throws RuntimeException 当指定的组不存在时
     */
    public function handlers(string|int|null $group = null): array
    {
        if ($group) {
            $group = is_string($group) ? hash_code($group) : $group;
            if (!isset($this->configure->groups[$group])) {
                throw new RuntimeException('Handler group not exists');
            }

            $handlers = $this->configure->groups[$group]['handlers'];
        } else {
            $handlers = $this->configure->handlers;
        }

        return array_map(fn($handler) => [
            'display_name' => $handler->handlerName(),
            'description' => $handler->handlerDescription(),
            'handler' => $handler::class,
        ], iterator_to_array((function ($handlers) {
            foreach ($handlers as $handler) {
                yield app()->make($handler);
            }
        })($handlers)));
    }

    /**
     * 创建处理器实体
     * 
     * 在数据库中创建一个新的处理器实体记录。
     * 处理器实体是处理器类的持久化实例，包含特定的配置和状态。
     * 
     * @param string|int $group 所属组名称或ID
     * @param string $name 实体名称（应该是唯一的）
     * @param string $handler 处理器类名或别名（必须已注册）
     * @param Repository|null $config 配置信息
     * @param string|null $displayName 显示名称，默认使用 name
     * @param string|null $description 描述信息
     * @return Handler 创建的处理器实体模型
     * @throws RuntimeException 当组不存在、处理器未注册或保存失败时
     */
    public function createEntityHandler(
        string|int $group,
        string $name,
        string $handler,
        ?Repository $config = null,
        ?string $displayName = null,
        ?string $description = ''
    ): Handler {
        $group = is_string($group) ? hash_code($group) : $group;

        if (!isset($this->configure->groups[$group])) {
            throw new RuntimeException('Handler group not exists');
        }

        // 尝试通过别名解析处理器类名
        $handler = $this->resolveHandlerClass($handler);

        if (!in_array($handler, $this->configure->handlers)) {
            throw new RuntimeException('Handler class not exists');
        }

        if (!$config) {
            $config = new Repository([]);
        }

        $entityInstance = new ($this->configure->model)();
        $entityInstance->forceFill([
            'group_id' => $group,
            'name' => $name,
            'handler' => $handler,
            'config' => $config->all(),
            'display_name' => $displayName ?? $name,
            'description' => $description ?? '',
            'enabled' => true
        ]);

        if (!$entityInstance->save()) {
            throw new RuntimeException('Save entity failed');
        }

        $this->cache->forget(sprintf('handler:entities:%d', $group));
        $this->cache->forget('handler:entities');

        return $entityInstance;
    }

    /**
     * 获取指定组的所有处理器实体
     * 
     * 从数据库或缓存中获取指定组的所有处理器实体。
     * 结果会被永久缓存，直到有新的实体被创建或更新。
     * 
     * @param string|int $group 处理器组名称或ID
     * @return Handler[] 处理器实体数组
     */
    public function entityHandlers(string|int $group): array
    {
        $group = is_string($group) ? hash_code($group) : $group;

        /** @var Handler[] $entities */
        $entities = $this->cache->rememberForever(sprintf('handler:entities:%d', $group), function () use ($group) {
            return $this->configure->model::query()->where('group_id', $group)->get()->all();
        });

        return $entities;
    }

    /**
     * 获取所有处理器实体
     * 
     * 从数据库或缓存中获取系统中所有的处理器实体。
     * 结果会被永久缓存，直到有新的实体被创建或更新。
     * 
     * @return Collection 处理器实体集合
     */
    public function getAllEntityHandlers(): Collection
    {
        return collect($this->cache->rememberForever('handler:entities', function () {
            return $this->configure->model::query()->get()->all();
        }));
    }

    /**
     * 获取单个处理器实体
     * 
     * 根据名称或ID查找单个处理器实体。
     * 如果传入的是字符串名称，会自动转换为 hash_code。
     * 
     * @param string|int $name 处理器实体名称或ID
     * @return Handler|null 处理器实体，不存在时返回 null
     */
    public function entityHandler(string|int $name): ?Handler
    {
        $entities = $this->getAllEntityHandlers();

        $name = is_string($name) ? hash_code($name) : $name;

        return collect($entities)->where('id', $name)->first();
    }

    /**
     * 检查处理器实体是否存在
     * 
     * 判断指定名称或ID的处理器实体是否存在。
     * 
     * @param string|int $name 处理器实体名称或ID
     * @return bool 存在返回 true，否则返回 false
     */
    public function existsEntityHandler(string|int $name): bool
    {
        return !!$this->getAllEntityHandlers()->where('id', is_string($name) ? hash_code($name) : $name)->count();
    }

    /**
     * 创建处理器实例
     *
     * @param string|int $name 处理器名称或ID
     * @return BaseHandler
     * @throws RuntimeException
     * @throws BindingResolutionException
     */
    public function createHandlerInstance(string|int $name): BaseHandler
    {
        // 获取实体处理器
        $entity = $this->entityHandler($name);
        
        if (!$entity) {
            throw new RuntimeException(sprintf('Handler entity "%s" not found', $name));
        }
        
        // 验证处理器类是否存在并且已注册
        if (!class_exists($entity->handler) || !in_array($entity->handler, $this->configure->handlers)) {
            throw new RuntimeException(sprintf('Handler class "%s" not found or not registered', $entity->handler));
        }
        
        // 创建处理器实例
        /** @var BaseHandler $handler */
        $handler = app()->make($entity->handler);
        
        // 设置配置
        if ($entity->config) {
            $configClass = $handler::configurationRepository();
            $config = new $configClass($entity->config);
            $handler->withConfig($config);
        }
        
        return $handler->withEntityId($entity->id);
    }

    /**
     * 获取纯处理器实例
     *
     * 获取不需要数据库实体的处理器实例。
     * 这些处理器通常作为单例使用，不支持多实例配置。
     *
     * @param string $handlerClass 处理器类名或别名
     * @param array|Repository|null $config 可选的配置
     * @return BaseHandler 处理器实例
     * @throws RuntimeException 处理器未注册或不是纯处理器时抛出异常
     * @throws BindingResolutionException
     */
    public function getPureHandler(string $handlerClass, array|Repository|null $config = null): BaseHandler
    {
        // 尝试通过别名解析处理器类名
        $handlerClass = $this->resolveHandlerClass($handlerClass);

        // 检查处理器是否已注册
        if (!in_array($handlerClass, $this->configure->handlers)) {
            throw new RuntimeException(sprintf('Handler class "%s" not registered', $handlerClass));
        }

        // 检查是否为纯处理器
        if ($handlerClass::requiresEntity()) {
            throw new RuntimeException(sprintf('Handler "%s" requires entity, use createEntityHandler() instead', $handlerClass));
        }

        /** @var BaseHandler $handler */
        $handler = app()->make($handlerClass);

        // 如果提供了配置，设置配置
        if ($config !== null) {
            $handler->withConfig($config);
        }

        return $handler;
    }

    /**
     * 获取指定组的所有纯处理器类
     * 
     * 返回指定组中所有不需要实体的处理器类名。
     * 
     * @param string|int $group 处理器组名称或ID
     * @return array 纯处理器类名数组
     */
    public function getPureHandlerClasses(string|int $group): array
    {
        $group = is_string($group) ? hash_code($group) : $group;
        
        if (!isset($this->configure->groups[$group])) {
            return [];
        }

        $pureHandlers = [];
        foreach ($this->configure->handlers as $handlerClass) {
            // 检查处理器是否属于该组
            $groupHandlers = $this->configure->groupHandlers[$group] ?? [];
            if (in_array($handlerClass, $groupHandlers) && !$handlerClass::requiresEntity()) {
                $pureHandlers[] = $handlerClass;
            }
        }

        return $pureHandlers;
    }

    /**
     * 解析处理器类名
     * 
     * 如果传入的是别名，返回实际的处理器类名。
     * 如果传入的已经是类名，直接返回。
     * 
     * @param string $handlerClassOrAlias 处理器类名或别名
     * @return class-string<BaseHandler> 处理器类名
     */
    protected function resolveHandlerClass(string $handlerClassOrAlias): string
    {
        // 如果是类名并且存在，直接返回
        if (class_exists($handlerClassOrAlias)) {
            return $handlerClassOrAlias;
        }

        // 尝试通过别名解析
        $aliasHash = hash_code($handlerClassOrAlias);
        if (isset($this->configure->handlerAliases[$aliasHash])) {
            return $this->configure->handlerAliases[$aliasHash];
        }

        // 返回原始值，让后续处理报错
        return $handlerClassOrAlias;
    }

    /**
     * 通过别名获取处理器类名
     * 
     * @param string $alias 处理器别名
     * @return string|null 处理器类名，不存在时返回 null
     */
    public function getHandlerClassByAlias(string $alias): ?string
    {
        $aliasHash = hash_code($alias);
        return $this->configure->handlerAliases[$aliasHash] ?? null;
    }
}
