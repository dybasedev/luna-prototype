<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;

/**
 * Luna 模块构建配置对象基类
 *
 * 提供在 Laravel 框架中服务容器注册的方法，提供对模块的配置
 */
abstract class LunaModuleConfigure
{
    protected ?Application $app = null {
        get {
            return $this->app ?: $this->app = app();
        }
    }

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
     * 模块服务提供者，用于在 Laravel 服务容器中注册模块服务
     *
     * 基本上可以直接使用 register 和 boot 来实现，但对于需要依赖服务提供者提供的功能时，
     * 可以直接选用该方法返回一个 ServiceProvider 类。
     *
     * 注意，该方法和 register 方法以及 boot 方法是可以共存的，请确保顺序正确，否则可能会导致错误。
     *
     * @return string|null
     */
    public function serviceProvider(): ?string
    {
        return null;
    }

    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
    }

    /**
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {

    }
}