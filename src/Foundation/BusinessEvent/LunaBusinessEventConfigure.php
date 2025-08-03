<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Luna 业务事件模块配置类
 * 
 * 提供业务事件的管理和配置功能，支持事件分组、事件处理器绑定
 * 以及事件的触发和处理机制
 * 
 * @package Dybasedev\LunaPrototype\Foundation\BusinessEvent
 */
class LunaBusinessEventConfigure extends LunaModuleConfigure
{
    /**
     * 业务事件模型类名
     * 
     * @var class-string<BusinessEvent>
     */
    protected(set) string $model = BusinessEvent::class;

    /**
     * 事件分组集合
     * 
     * 键为组的哈希码，值包含组名和显示名
     * 
     * @var array<string, array{name: string, display_name: string}>
     */
    protected(set) array $groups = [];

    /**
     * 获取模块名称
     * 
     * @return string
     */
    public function name(): string
    {
        return 'luna.business-event';
    }

    /**
     * 设置业务事件模型类
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
     * 创建或配置事件分组
     * 
     * @param string $name 分组名称
     * @param string|null $displayName 显示名称
     * @return static
     * @throws RuntimeException 当使用保留组名 "common" 时抛出异常
     */
    public function group(string $name, ?string $displayName = null): static
    {
        if ($name === 'common') {
            throw new RuntimeException('Group name "common" is reserved');
        }

        $this->groups[hash_code($name)] = [
            'name' => $name,
            'display_name' => $displayName ?? $name,
        ];

        return $this;
    }

    /**
     * 添加事件分组（group 方法的别名）
     *
     * @param string $name 分组名称
     * @param string|null $displayName 显示名称
     * @return static
     */
    public function addGroup(string $name, ?string $displayName = null): static
    {
        return $this->group($name, $displayName);
    }

    /**
     * 注册业务事件服务到容器
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.business-event', function ($app) {
            return new LunaBusinessEvent(
                $app->make(LunaBusinessEventConfigure::class),
                $app->make(LunaHandler::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.business-event', LunaBusinessEvent::class);
    }

    /**
     * 启动业务事件模块
     * 
     * 注册默认的业务事件处理器到处理器组
     * 
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        $container->make(LunaHandlerConfigure::class)->group('business-event', '业务事件', function ($register) {
            $register->handler(DefaultBusinessEventHandler::class);
        });
    }


}