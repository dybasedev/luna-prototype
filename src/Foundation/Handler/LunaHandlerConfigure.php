<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Closure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Luna 处理器配置类
 * 
 * 用于管理和配置系统中的各种处理器组和处理器
 * 提供了处理器的分组管理、注册和依赖注入功能
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Handler
 */
class LunaHandlerConfigure extends LunaModuleConfigure
{
    /**
     * 处理器组集合
     * 
     * 键为组的哈希码，值包含组名、显示名和处理器列表
     * 
     * @var array<string, array{name: string, display_name: ?string, handlers?: array}>
     */
    protected(set) array $groups = [];

    /**
     * 所有已注册的处理器类名列表
     * 
     * @var array<class-string>
     */
    protected(set) array $handlers = [];

    /**
     * 处理器模型类名
     * 
     * @var class-string<Models\Handler>
     */
    protected(set) string $model = Models\Handler::class;

    /**
     * 获取模块名称
     * 
     * @return string
     */
    public function name(): string
    {
        return 'luna.handler';
    }

    /**
     * 向指定组添加处理器
     * 
     * @param string $group 组名
     * @param string $handlerClass 处理器类名
     * @return static
     * @throws RuntimeException 当组不存在时抛出异常
     */
    public function handler(string $group, string $handlerClass): static
    {
        if (!isset($this->groups[hash_code($group)])) {
            throw new RuntimeException('Handler group not exists.');
        }

        $this->groups[hash_code($group)]['handlers'][] = $handlerClass;

        if (!in_array($handlerClass, $this->handlers)) {
            $this->handlers[] = $handlerClass;
        }

        return $this;
    }

    /**
     * 创建或配置处理器组
     * 
     * @param string $name 组名（唯一标识）
     * @param string|null $displayName 显示名称
     * @param Closure|null $handlerRegister 处理器注册回调函数
     * @return static
     */
    public function group(string $name, ?string $displayName = null, ?Closure $handlerRegister = null): static
    {
        $this->groups[hash_code($name)] = [
            'name' => $name,
            'display_name' => $displayName,
        ];

        $handlerAppender = function (string $handlerClass) use ($name) {
            $this->handler($name, $handlerClass);
        };

        if ($handlerRegister) {
            $handlerRegister(
                new class($this, $handlerAppender) {
                    public function __construct(protected LunaHandlerConfigure $configure, protected Closure $handlerAppender)
                    {
                    }

                    public function handler(string $handlerClass): static
                    {
                        if (!class_exists($handlerClass)) {
                            throw new RuntimeException('Handler class not exists.');
                        }

                        ($this->handlerAppender)($handlerClass);
                        return $this;
                    }
                }
            );
        }

        return $this;
    }

    /**
     * 设置处理器模型类
     * 
     * @param string $model 模型类名
     * @return static
     */
    public function useModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * 添加处理器组（group 方法的别名）
     *
     * @param string $name 组名
     * @param string|null $displayName 显示名称
     * @return static
     */
    public function addGroup(string $name, ?string $displayName = null): static
    {
        return $this->group($name, $displayName);
    }

    /**
     * 添加处理器到列表
     *
     * @param string $handlerClass 处理器类名
     * @return static
     */
    public function addHandler(string $handlerClass): static
    {
        if (!in_array($handlerClass, $this->handlers)) {
            $this->handlers[] = $handlerClass;
        }
        return $this;
    }

    /**
     * 注册处理器服务到容器
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.handler', function ($app) {
            return new LunaHandler(
                $app->make(LunaHandlerConfigure::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.handler', LunaHandler::class);
    }

}