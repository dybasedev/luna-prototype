<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * 处理器基类
 */
abstract class BaseHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    abstract public function handlerName(): string;

    /**
     * 获取处理器描述
     *
     * @return string
     */
    abstract public function handlerDescription(): string;

    /**
     * 关联的配置仓库类
     *
     * @return class-string<Repository>
     */
    public static function configurationRepository(): string
    {
        return Repository::class;
    }

    /**
     * @var Repository|null 配置信息
     */
    protected(set) ?Repository $config = null;

    /**
     * @var int|null 处理器ID
     */
    private ?int $_handlerId = null;
    
    public ?int $handlerId {
        get => $this->_handlerId;
        set {
            $this->_handlerId = $value;
        }
    }

    /**
     * @param array|Repository $config
     * @return $this
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
     * @return Repository
     */
    public function getConfig(): Repository
    {
        if ($this->config === null) {
            $this->config = new (static::configurationRepository())([]);
        }
        
        return $this->config;
    }
}