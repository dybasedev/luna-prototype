<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 模块构建配置对象基类
 *
 * 提供在 Laravel 框架中服务容器注册的方法，提供对模块的配置
 */
abstract class LunaModuleConfigure
{
    /**
     * 模块名称，用于作为唯一标识，便于覆盖
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * @throws BindingResolutionException
     */
    public static function create(): static
    {
        return app()->make(static::class);
    }

    /**
     * 返回一个放置在 Laravel 服务容器中的闭包
     *
     * @return LunaModuleConfigure|Closure
     */
    public function build(): static|Closure
    {
        return $this;
    }

    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
    }
}