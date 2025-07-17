<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Illuminate\Contracts\Container\Container;

/**
 * Luna 应用程序配置类
 *
 * 负责配置 Luna 应用程序的各项设置，包括安装程序、备份对象等。
 * 这个类扩展了 LunaModuleConfigure，为应用程序提供了特定的配置选项。
 *
 * 主要配置内容：
 * - 系统安装程序列表
 * - 可备份对象列表
 * - 应用程序级别的服务注册
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaApplicationConfigure extends LunaModuleConfigure
{
    /**
     * 系统安装程序列表
     *
     * 存储实现了 Installation 接口的类名数组。
     * 这些安装程序会在系统安装过程中被执行，
     * 用于初始化数据库、创建默认数据等操作。
     *
     * @var class-string<Installation>[]
     */
    protected(set) array $installations = [];

    /**
     * 可备份对象列表
     *
     * 存储实现了 Backupable 接口的类名数组。
     * 这些对象可以在系统备份过程中被调用，
     * 用于备份重要的业务数据和配置信息。
     *
     * @var class-string<Backupable>[]
     */
    protected(set) array $backupableObjects = [];

    /**
     * 获取应用程序模块名称
     *
     * @return string 返回应用程序模块的标识名称
     */
    public function name(): string
    {
        return 'luna.app';
    }

    /**
     * 添加系统安装程序
     *
     * 将一个安装程序类添加到安装程序列表中。
     * 安装程序会在系统安装过程中按照添加顺序被执行。
     *
     * @param string $installation 实现了 Installation 接口的类名
     * @return static 返回当前实例以支持链式调用
     */
    public function installation(string $installation): static
    {
        $this->installations[] = $installation;
        return $this;
    }

    /**
     * 注册应用程序服务到容器
     *
     * 在服务容器中注册 Luna 应用程序的核心服务。
     * 这包括应用程序实例的单例注册和相关的服务别名。
     *
     * 注册的服务：
     * - 'luna': LunaApplication 单例实例
     * - 'luna.app': 应用程序服务别名
     *
     * @param Container $container Laravel 服务容器实例
     * @return void
     */
    public function register(Container $container): void
    {
        // 注册 Luna 应用程序单例
        $container->singleton('luna', function ($app) {
            return new LunaApplication(
                $app->make(LunaApplicationConfigure::class),
            );
        });

        // 设置服务别名，方便多种方式访问
        $container->alias('luna', LunaApplication::class);
        $container->alias('luna', 'luna.app');
    }


}