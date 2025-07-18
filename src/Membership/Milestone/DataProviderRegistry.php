<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\DataProvider;
use Illuminate\Support\Collection;

/**
 * 数据提供者注册表
 * 
 * 管理和存储所有的数据提供者实例
 */
class DataProviderRegistry
{
    /**
     * 已注册的数据提供者
     *
     * @var Collection<string, DataProvider>
     */
    protected Collection $providers;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->providers = new Collection();
    }

    /**
     * 注册数据提供者
     *
     * @param DataProvider $provider
     * @return static
     */
    public function register(DataProvider $provider): static
    {
        $this->providers->put($provider->getName(), $provider);
        return $this;
    }

    /**
     * 获取数据提供者
     *
     * @param string $name
     * @return DataProvider|null
     */
    public function get(string $name): ?DataProvider
    {
        return $this->providers->get($name);
    }

    /**
     * 检查是否存在指定的数据提供者
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->providers->has($name);
    }

    /**
     * 移除数据提供者
     *
     * @param string $name
     * @return static
     */
    public function remove(string $name): static
    {
        $this->providers->forget($name);
        return $this;
    }

    /**
     * 获取所有数据提供者
     *
     * @return Collection<string, DataProvider>
     */
    public function all(): Collection
    {
        return $this->providers;
    }

    /**
     * 清空所有数据提供者
     *
     * @return static
     */
    public function clear(): static
    {
        $this->providers = new Collection();
        return $this;
    }
}