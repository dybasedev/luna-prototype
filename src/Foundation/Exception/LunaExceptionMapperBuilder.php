<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

/**
 * Luna 异常映射构建器
 * 
 * 提供流畅的接口来配置异常映射规则，将特定类型的异常转换为 LunaException。
 * 支持链式调用，可以灵活地定义错误消息、HTTP 状态码、报告行为、前端行为和携带数据。
 * 
 * @template TClass of Throwable 要映射的异常类型
 * 
 * @example
 * ```php
 * $builder = new LunaExceptionMapperBuilder(ValidationException::class)
 *     ->message('验证失败')
 *     ->httpStatus(422)
 *     ->dontReport()
 *     ->behaviour(['action' => 'show_errors'])
 *     ->data(function ($e) {
 *         return ['errors' => $e->errors()];
 *     });
 * ```
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception
 */
class LunaExceptionMapperBuilder
{
    /**
     * 映射配置内容
     * 
     * 存储构建过程中设置的所有配置项，包括 message、httpStatus、reportable、behaviour 和 data。
     * 
     * @var array
     */
    protected(set) array $body = [];
    
    /**
     * 创建新的构建器实例（静态工厂方法）
     * 
     * 提供更流畅的链式调用体验。
     * 
     * @template T of Throwable
     * @param class-string<T> $exceptionClass 要映射的异常类名
     * @return static<T> 新的构建器实例
     * 
     * @example
     * LunaExceptionMapperBuilder::for(NotFoundException::class)
     *     ->message('资源不存在')
     *     ->httpStatus(404)
     *     ->dontReport();
     */
    public static function for(string $exceptionClass): static
    {
        return new static($exceptionClass);
    }

    /**
     * 要映射的异常类名
     * 
     * @var class-string<TClass>
     */
    protected(set) string $exceptionClass;

    /**
     * 构造函数
     * 
     * @param class-string<TClass> $exceptionClass 要映射的异常类名
     */
    public function __construct(string $exceptionClass)
    {
        $this->exceptionClass = $exceptionClass;
    }

    /**
     * 设置错误消息
     * 
     * 支持静态字符串或动态闭包。闭包接收异常实例作为参数，
     * 可以根据异常内容动态生成消息。
     * 
     * @param string|Closure(TClass): string $message 错误消息或生成消息的闭包
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * // 静态消息
     * ->message('用户不存在')
     * 
     * // 动态消息
     * ->message(fn($e) => '用户 ' . $e->getUserId() . ' 不存在')
     */
    public function message(string|Closure $message): static
    {
        $this->body['message'] = $message;
        return $this;
    }

    /**
     * 设置 HTTP 状态码
     * 
     * 支持静态数值或动态闭包。闭包可以根据异常类型或内容
     * 返回不同的状态码。
     * 
     * @param int|Closure(TClass): int $httpStatus HTTP 状态码或生成状态码的闭包
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * // 静态状态码
     * ->httpStatus(404)
     * 
     * // 动态状态码
     * ->httpStatus(fn($e) => $e->isCritical() ? 500 : 400)
     */
    public function httpStatus(int|Closure $httpStatus): static
    {
        $this->body['httpStatus'] = $httpStatus;
        return $this;
    }

    /**
     * 设置是否可报告
     * 
     * 控制异常是否应该被记录到日志或监控系统。
     * 支持静态布尔值或动态闭包。
     * 
     * @param bool|Closure(TClass): bool $reportable 是否可报告或判断闭包
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * // 禁用报告
     * ->reportable(false)
     * 
     * // 根据条件判断
     * ->reportable(fn($e) => $e->getSeverity() > ErrorLevel::WARNING)
     */
    public function reportable(bool|Closure $reportable): static
    {
        $this->body['reportable'] = $reportable;
        return $this;
    }

    /**
     * 禁用异常报告
     * 
     * 这是 reportable(false) 的便捷方法，用于明确指出该异常不需要报告。
     * 适用于预期的业务异常，如验证失败、资源不存在等。
     * 
     * @return static 返回自身以支持链式调用
     */
    public function dontReport(): static
    {
        $this->reportable(false);
        return $this;
    }

    /**
     * 设置前端行为
     * 
     * 定义前端在接收到该异常时应该执行的行为。
     * 支持字符串、数组或动态闭包。
     * 
     * @param string|array|Closure(TClass): (string|array|null)|null $behaviour 前端行为配置
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * // 字符串行为
     * ->behaviour('refresh')
     * 
     * // 数组行为
     * ->behaviour(['action' => 'redirect', 'url' => '/login'])
     * 
     * // 动态行为
     * ->behaviour(fn($e) => $e->isAuthError() ? ['action' => 'logout'] : null)
     */
    public function behaviour(string|array|Closure|null $behaviour): static
    {
        $this->body['behaviour'] = $behaviour;
        return $this;
    }

    /**
     * 设置携带数据
     * 
     * 向前端传递额外的上下文信息，帮助前端更好地处理异常。
     * 支持静态数组或动态闭包。
     * 
     * @param array|Closure(TClass): (array|null)|null $data 携带的数据或生成数据的闭包
     * @return static 返回自身以支持链式调用
     * 
     * @example
     * // 静态数据
     * ->data(['field' => 'email', 'value' => 'invalid@email'])
     * 
     * // 动态数据
     * ->data(fn($e) => [
     *     'errors' => $e->getErrors(),
     *     'failed_rules' => $e->getFailedRules()
     * ])
     */
    public function data(array|Closure|null $data): static
    {
        $this->body['data'] = $data;
        return $this;
    }

    /**
     * 构建映射器闭包
     * 
     * 将所有配置转换为一个可执行的映射器闭包。
     * 该闭包接收异常实例，返回包含 message、behaviour、data、httpStatus 和 report 的数组。
     * 
     * @return Closure 映射器闭包
     */
    public function build(): Closure
    {
        $body = $this->body;
        return function (Throwable $throwable) use ($body) {
            if (isset($body['message'])) {
                if ($body['message'] instanceof Closure) {
                    $body['message'] = $body['message']($throwable);
                }
            } else {
                $body['message'] = $throwable->getMessage();
            }

            if (isset($body['httpStatus'])) {
                if ($body['httpStatus'] instanceof Closure) {
                    $body['httpStatus'] = $body['httpStatus']($throwable);
                }
            } else {
                $body['httpStatus'] = 500;
            }

            if (isset($body['reportable'])) {
                if ($body['reportable'] instanceof Closure) {
                    $body['reportable'] = (bool)$body['reportable']($throwable);
                }
            } else {
                $body['reportable'] = app()->make(ExceptionHandler::class)->shouldReport($throwable);
            }

            if (isset($body['behaviour'])) {
                if ($body['behaviour'] instanceof Closure) {
                    $body['behaviour'] = $body['behaviour']($throwable);
                }
            } else {
                $body['behaviour'] = null;
            }

            if (isset($body['data'])) {
                if ($body['data'] instanceof Closure) {
                    $body['data'] = $body['data']($throwable);
                }
            } else {
                $body['data'] = null;
            }

            return [
                'message' => $body['message'],
                'behaviour' => $body['behaviour'],
                'data' => $body['data'],
                'httpStatus' => $body['httpStatus'],
                'report' => $body['reportable'],
            ];
        };
    }
}