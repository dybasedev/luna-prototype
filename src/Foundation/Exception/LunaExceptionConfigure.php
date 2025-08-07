<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Closure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Throwable;

/**
 * Luna 异常配置类
 * 
 * 提供统一的异常处理配置，支持异常映射、自定义报告器和响应格式控制。
 * 可以将各种异常类型映射为统一的 LunaException，并定义其显示消息、HTTP 状态码等。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception
 */
class LunaExceptionConfigure extends LunaModuleConfigure
{
    /**
     * 异常映射器集合
     * 
     * 存储异常类名到映射器函数的映射关系。
     * 映射器函数接收异常实例，返回包含 message、httpStatus、report、behaviour、data 的数组。
     * 
     * @var array<class-string<Throwable>, Closure>
     */
    protected(set) array $exceptionMappers = [];

    /**
     * 是否总是返回 JSON 格式响应
     * 
     * 当设置为 true 时，无论请求是否期望 JSON，都会返回 JSON 格式的错误响应。
     * 适用于纯 API 应用场景。
     * 
     * @var bool
     */
    protected(set) bool $alwaysJsonRender = false;

    /**
     * 自定义异常报告器
     * 
     * 用于自定义异常的报告行为，例如发送到特定的日志服务或错误追踪系统。
     * 如果未设置，将使用默认的日志报告器。
     * 
     * @var Closure|null
     */
    protected(set) ?Closure $reporter = null;

    /**
     * 获取模块名称
     * 
     * @return string 返回异常模块的唯一标识符
     */
    public function name(): string
    {
        return 'luna.exception';
    }

    /**
     * 包装异常类映射
     * 
     * 将指定的异常类映射为 LunaException，支持多种配置方式：
     * 1. 使用 LunaExceptionMapperBuilder 进行链式配置
     * 2. 使用字符串定义简单的错误消息
     * 3. 使用闭包进行完全自定义的映射处理
     * 
     * @param string|LunaExceptionMapperBuilder $exceptionClass 要映射的异常类名或构建器实例
     * @param string|Closure|null $mapper 映射器，可以是错误消息字符串或自定义闭包
     * @param int $httpStatus HTTP 状态码，默认 500
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * // 使用字符串消息
     * $configure->wrap(ValidationException::class, '验证失败', 422);
     * 
     * // 使用构建器
     * $configure->wrap(
     *     LunaExceptionMapperBuilder::for(NotFoundException::class)
     *         ->message('资源不存在')
     *         ->httpStatus(404)
     *         ->dontReport()
     * );
     */
    public function wrap(
        string|LunaExceptionMapperBuilder $exceptionClass,
        string|Closure|null $mapper = null,
        int $httpStatus = 500
    ): static {
        if ($exceptionClass instanceof LunaExceptionMapperBuilder) {
            $mapper = $exceptionClass->build();
            $exceptionClass = $exceptionClass->exceptionClass;
        } else {
            if (is_string($mapper)) {
                $mapper = function ($exception) use ($httpStatus, $mapper) {
                    // 获取 laravel 自带的 ExceptionHandler，判定默认不进行报告的异常
                    return [
                        'message' => $mapper,
                        'httpStatus' => $httpStatus,
                        'report' => app()->make(ExceptionHandler::class)->shouldReport($exception),
                        'behaviour' => null,
                        'data' => null,
                    ];
                };
            }

            // 映射器不能为空
            if (is_null($mapper)) {
                return $this;
            }
        }

        $this->exceptionMappers[$exceptionClass] = $mapper;

        return $this;
    }

    /**
     * 设置是否总是返回 JSON 响应
     * 
     * 启用后，无论客户端请求头如何设置，都会返回 JSON 格式的错误响应。
     * 这对于纯 API 应用特别有用，可以确保一致的响应格式。
     * 
     * @param bool $alwaysJsonRender 是否总是返回 JSON，默认 true
     * @return static 返回自身以支持链式调用
     */
    public function alwaysJsonRender(bool $alwaysJsonRender = true): static
    {
        $this->alwaysJsonRender = $alwaysJsonRender;
        return $this;
    }

    /**
     * 设置自定义异常报告器
     * 
     * 报告器用于记录异常信息，可以将异常发送到日志、监控系统或其他服务。
     * 报告器闭包接收一个 Throwable 参数。
     * 
     * @param Closure $reporter 异常报告器闭包
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * $configure->reporter(function (Throwable $e) {
     *     // 发送到外部监控服务
     *     Sentry::captureException($e);
     *     
     *     // 同时记录到本地日志
     *     Log::channel('errors')->error($e->getMessage(), [
     *         'exception' => $e
     *     ]);
     * });
     */
    public function reporter(Closure $reporter): static
    {
        $this->reporter = $reporter;
        return $this;
    }

    /**
     * 注册异常处理服务
     * 
     * 在 Laravel 的异常处理器解析后，注册全局异常映射。
     * 将所有非 LunaException 的异常自动转换为 LunaException。
     * 
     * @param Container $container 服务容器
     * @return void
     * @throws BindingResolutionException 当容器解析失败时抛出
     */
    public function register(Container $container): void
    {
        $container->afterResolving(Handler::class, function (Handler $handler) {
            $handler->map(Throwable::class,
                fn($throwable) => $throwable instanceof LunaException ? $throwable : LunaException::create($throwable));
        });
    }


}