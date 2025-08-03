<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 配置管理模块配置类
 * 
 * 提供系统级别的配置管理功能，支持配置的存储、读取、版本控制
 * 以及配置仓库的绑定和管理
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Configuration
 */
class LunaConfigurationConfigure extends LunaModuleConfigure
{
    /**
     * 配置模型类名
     * 
     * @var class-string<Models\Configuration>
     */
    protected(set) string $model = Models\Configuration::class;

    /**
     * 配置值版本模型类名
     * 
     * @var class-string<Models\ConfigurationValue>
     */
    protected(set) string $versionModel = Models\ConfigurationValue::class;

    /**
     * 绑定的配置仓库类
     * 
     * 按组和名称组织的配置仓库绑定关系
     *
     * @var array<string, array<string, class-string>>
     */
    protected(set) array $repositoryBinds = [];

    /**
     * 默认的配置仓库类
     *
     * @var class-string<Repository>
     */
    protected(set) string $defaultRepository = Repository::class;

    /**
     * 获取模块名称
     * 
     * @return string
     */
    public function name(): string
    {
        return 'luna.config';
    }


    /**
     * 设置配置模型类
     * 
     * @param string $class 模型类名
     * @return static
     */
    public function useModel(string $class): static
    {
        $this->model = $class;
        return $this;
    }

    /**
     * 设置配置值版本模型类
     * 
     * @param string $class 模型类名
     * @return static
     */
    public function useVersionModel(string $class): static
    {
        $this->versionModel = $class;
        return $this;
    }

    /**
     * 绑定特定组和名称的配置仓库
     * 
     * @param string $group 配置组
     * @param string $name 配置名称
     * @param string $repository 仓库类名
     * @return static
     */
    public function bindRepository(string $group, string $name, string $repository): static
    {
        $this->repositoryBinds[$group][$name] = $repository;

        return $this;
    }

    /**
     * 注册配置服务到容器
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.config', function ($app) {
            return new LunaConfiguration(
                $app->make(LunaConfigurationConfigure::class),
                $app->make('cache.store'),
            );
        });
        $container->alias('luna.config', LunaConfiguration::class);
    }


}