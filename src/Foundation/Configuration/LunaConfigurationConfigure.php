<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\Container;

class LunaConfigurationConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<Models\Configuration>
     */
    protected(set) string $model = Models\Configuration::class;

    /**
     * @var class-string<Models\ConfigurationValue>
     */
    protected(set) string $versionModel = Models\ConfigurationValue::class;

    /**
     * 绑定的配置仓库类
     *
     * @var array
     */
    protected(set) array $repositoryBinds = [];

    /**
     * 默认的配置仓库
     *
     * @var string
     */
    protected(set) string $defaultRepository = Repository::class;

    public function name(): string
    {
        return 'luna.config';
    }


    public function useModel(string $class): static
    {
        $this->model = $class;
        return $this;
    }

    public function useVersionModel(string $class): static
    {
        $this->versionModel = $class;
        return $this;
    }

    public function bindRepository(string $group, string $name, string $repository): static
    {
        $this->repositoryBinds[$group][$name] = $repository;

        return $this;
    }

    public function register(Container $container): void
    {
        $container->singleton('luna.config', LunaConfiguration::class);
//        $container->alias('luna.config', LunaConfiguration::class);
    }


}