<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;

/**
 * Luna 模块构建配置对象基类
 *
 * 提供在 Laravel 框架中服务容器注册的方法，提供对模块的配置。
 * 这个抽象类定义了模块配置的标准接口，包括服务容器注册、
 * 模块依赖管理和生命周期管理。
 *
 * 每个模块都需要一个对应的配置类来继承此基类，并实现相应的配置方法。
 * 配置类负责：
 * - 定义模块的唯一标识名称
 * - 声明模块的依赖关系
 * - 在服务容器中注册模块服务
 * - 处理模块的启动逻辑
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class LunaModuleConfigure
{
    /**
     * Laravel 应用实例
     *
     * 通过只读属性访问器延迟加载 Laravel 应用实例。
     * 这样可以避免在类实例化时立即依赖应用实例，
     * 提高了类的灵活性和可测试性。
     *
     * @var Application|null
     */
    protected ?Application $app = null {
        get {
            return $this->app ?: $this->app = app();
        }
    }

    /**
     * 模块名称，用于作为唯一标识，便于覆盖
     *
     * 返回模块的唯一标识名称，用于在系统中区分不同的模块。
     * 这个名称通常以点号分隔的格式命名，如 'luna.assets-account'。
     * 
     * 模块名称的命名规范：
     * - 使用小写字母和连字符
     * - 以 'luna.' 开头
     * - 具有描述性，能够清楚表明模块的功能
     *
     * @return string 模块的唯一标识名称
     */
    abstract public function name(): string;

    /**
     * 模块依赖的其他模块名称
     *
     * 返回当前模块依赖的其他模块名称数组。系统会根据这些依赖关系
     * 来确定模块的加载顺序，确保依赖的模块在当前模块之前被加载。
     *
     * 依赖关系的作用：
     * - 确保模块加载的正确顺序
     * - 避免循环依赖问题
     * - 提供清晰的模块间关系说明
     *
     * @return array<string> 依赖的模块名称数组，默认为空数组（无依赖）
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * 创建模块配置实例
     *
     * 通过 Laravel 服务容器创建当前配置类的实例。
     * 这个静态方法提供了一种便捷的方式来获取配置实例，
     * 并自动解析依赖注入。
     *
     * @return static 当前配置类的实例
     * @throws BindingResolutionException 当服务容器无法解析类时抛出
     */
    public static function create(): static
    {
        return app()->make(static::class);
    }

    /**
     * 构建模块配置
     *
     * 返回一个可以放置在 Laravel 服务容器中的配置对象或闭包。
     * 默认情况下返回当前配置实例，但子类可以重写此方法来返回
     * 更复杂的配置逻辑（如闭包）。
     *
     * 这个方法在模块配置完成后调用，用于生成最终的配置对象。
     *
     * @return static|Closure 配置对象或配置闭包
     */
    public function build(): static|Closure
    {
        return $this;
    }

    /**
     * 模块服务提供者，用于在 Laravel 服务容器中注册模块服务
     *
     * 返回一个 Laravel ServiceProvider 类名，用于处理更复杂的服务注册逻辑。
     * 大多数情况下可以直接使用 register() 和 boot() 方法来完成服务注册，
     * 但当需要依赖服务提供者的特定功能时（如中间件注册、路由注册等），
     * 可以通过此方法返回一个自定义的 ServiceProvider 类。
     *
     * 注意事项：
     * - 此方法与 register() 和 boot() 方法可以共存
     * - 确保执行顺序正确，避免依赖冲突
     * - ServiceProvider 会在 register() 和 boot() 方法之前执行
     *
     * @return string|null ServiceProvider 类名，如果不需要则返回 null
     */
    public function serviceProvider(): ?string
    {
        return null;
    }

    /**
     * 注册模块服务到 Laravel 服务容器
     *
     * 在 Laravel 应用启动的 register 阶段调用此方法。
     * 用于向服务容器注册模块的服务、绑定接口实现、设置单例等。
     *
     * 这个方法应该只包含服务注册逻辑，不应该包含任何依赖于其他服务的代码，
     * 因为在 register 阶段其他服务可能还没有被注册。
     *
     * 常见的注册操作：
     * - 注册单例服务
     * - 绑定接口到具体实现
     * - 设置服务别名
     * - 注册配置值
     *
     * @param Container $container Laravel 服务容器实例
     * @return void
     */
    public function register(Container $container): void
    {
    }

    /**
     * 启动模块服务
     *
     * 在 Laravel 应用启动的 boot 阶段调用此方法。
     * 此时所有服务都已经注册完成，可以安全地使用其他服务。
     *
     * 这个方法用于执行模块的初始化逻辑，如：
     * - 注册事件监听器
     * - 发布资源文件
     * - 执行数据库迁移
     * - 注册中间件
     * - 配置第三方服务
     *
     * boot 方法与 register 方法的区别：
     * - register: 只注册服务，不使用其他服务
     * - boot: 可以使用已注册的服务，执行初始化逻辑
     *
     * @param Container $container Laravel 服务容器实例
     * @return void
     */
    public function boot(Container $container): void
    {

    }
}