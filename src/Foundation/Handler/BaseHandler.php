<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * 处理器基类
 * 
 * 提供统一的处理器接口和配置管理功能。
 * 处理器是 Luna Prototype 中用于执行特定业务逻辑的核心组件。
 * 
 * 处理器分为两种类型：
 * - 实体处理器（Entity Handler）：需要在数据库中存储配置，支持多实例
 * - 纯处理器（Pure Handler）：仅在代码中定义，通常为单例
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Handler
 */
abstract class BaseHandler
{
    /**
     * 配置信息实例
     *
     * 存储处理器的配置信息，通过 withConfig() 方法设置。
     * 使用 protected(set) 确保只能通过指定方法修改配置。
     *
     * @var Repository|null
     */
    protected(set) ?Repository $config = null;

    /**
     * 处理器ID访问器
     *
     * 提供对处理器ID的读写访问。
     */
    protected(set) ?int $handlerId = null;

    /**
     * 获取处理器名称
     * 
     * 返回处理器的唯一标识名称，用于注册和查找处理器。
     * 建议使用小写字母和连字符的格式，如：'user-auth'、'payment-gateway'
     *
     * @return string 处理器名称
     */
    abstract public function handlerName(): string;

    /**
     * 获取处理器描述
     * 
     * 返回处理器的功能描述，用于文档生成和调试。
     * 应该简洁明了地说明处理器的主要功能和用途。
     *
     * @return string 处理器描述
     */
    abstract public function handlerDescription(): string;

    /**
     * 判断处理器是否需要实体
     * 
     * 返回 true 表示该处理器需要在数据库中创建实体记录，支持多实例和持久化配置。
     * 返回 false 表示该处理器为纯代码定义，通常作为单例使用。
     * 
     * 子类可以重写此方法来指定处理器类型。
     * 
     * @return bool 是否需要实体
     */
    public static function requiresEntity(): bool
    {
        return true;
    }

    /**
     * 关联的配置仓库类
     * 
     * 返回处理器使用的配置仓库类名。
     * 子类可以重写此方法以使用自定义的配置仓库类。
     * 配置仓库用于存储和管理处理器的运行时配置。
     *
     * @return class-string<Repository> 配置仓库类名
     */
    public static function configurationRepository(): string
    {
        return Repository::class;
    }

    /**
     * 附加处理器实体 ID
     *
     * @param int $handlerId
     * @return $this
     */
    public function withEntityId(int $handlerId): static
    {
        $this->handlerId = $handlerId;
        return $this;
    }

    /**
     * 设置处理器配置
     * 
     * 支持传入数组或配置仓库实例。
     * 如果传入的是数组，会自动创建对应的配置仓库实例。
     * 如果传入的配置仓库类型不匹配，会尝试转换为正确的类型。
     * 
     * @param array|Repository $config 配置数组或配置仓库实例
     * @return static 返回自身以支持链式调用
     */
    public function withConfig(array|Repository $config): static
    {
        if ($config instanceof Repository) {
            if ($config::class === static::configurationRepository()) {
                $this->config = $config;
            } else {
                $this->config = static::configurationRepository()::fromRepository($config);
            }
        } else {
            $this->config = new (static::configurationRepository())($config);
        }

        return $this;
    }
    
    /**
     * 获取配置信息
     * 
     * 如果配置尚未初始化，会自动创建一个空的配置仓库实例。
     * 确保处理器始终有可用的配置对象，避免空指针异常。
     * 
     * @return Repository 配置仓库实例
     */
    public function getConfig(): Repository
    {
        if ($this->config === null) {
            $this->config = new (static::configurationRepository())([]);
        }
        
        return $this->config;
    }
}