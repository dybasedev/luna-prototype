<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Luna 配置管理器
 *
 * 作为配置系统的入口点，管理所有配置组。
 * 通过缓存机制提高配置读取性能，支持多配置组隔离。
 *
 * @package Dybasedev\LunaPrototype\Foundation\Configuration
 */
class LunaConfiguration
{
    /**
     * 配置组实例集合
     *
     * @var array<string, ConfigurationGroup>
     */
    protected array $groups = [];

    /**
     * 构造函数
     *
     * @param LunaConfigurationConfigure $configure 配置管理器配置
     * @param Cache $cache 缓存实例
     */
    public function __construct(
        protected LunaConfigurationConfigure $configure,
        protected Cache $cache
    ) {
    }

    /**
     * 获取或创建配置组
     *
     * 如果配置组实例已存在则直接返回，否则创建新的配置组实例并设置缓存。
     *
     * @param string $name 配置组名称
     * @return ConfigurationGroup 配置组实例
     */
    public function group(string $name): ConfigurationGroup
    {
        if (isset($this->groups[$name])) {
            return $this->groups[$name];
        }

        return $this->groups[$name] = new ConfigurationGroup($this->configure, $name)->withCache($this->cache);
    }
}