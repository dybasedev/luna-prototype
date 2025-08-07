<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Luna 统一异常类
 * 
 * 提供统一的异常处理机制，支持自定义显示消息、HTTP 状态码、行为控制和数据传递。
 * 所有异常最终都会被转换为此类型，以确保一致的错误响应格式。
 * 
 * 主要功能：
 * - 支持自定义前端显示消息，与内部错误消息分离
 * - 支持定义前端行为（如跳转、刷新等）
 * - 支持携带额外数据给前端
 * - 支持自定义 HTTP 响应状态码
 * - 支持异常报告控制
 * - 支持从前一个异常继承属性
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception
 */
class LunaException extends RuntimeException
{
    /**
     * 响应至前端的错误信息
     * 
     * 用于向用户展示的友好错误消息，可以与内部错误消息不同。
     * 例如，内部消息可能是 "Database connection failed"，
     * 而显示消息可以是 "系统繁忙，请稍后重试"。
     * 
     * @var string|null
     */
    protected(set) ?string $displayMessage = null;

    /**
     * 前端行为控制列表
     * 
     * 定义前端在接收到错误后应该执行的行为，如跳转、刷新、显示特定提示等。
     * 具体行为由前后端约定，例如：
     * - ['action' => 'redirect', 'url' => '/login'] 跳转到登录页
     * - ['action' => 'refresh'] 刷新当前页面
     * - ['action' => 'logout'] 执行登出操作
     * 
     * @var array|null
     */
    protected(set) array|null $behaviour = null;

    /**
     * 携带的额外数据
     * 
     * 可以向前端传递额外的上下文信息，帮助前端更好地处理错误。
     * 例如：验证错误的具体字段、剩余重试次数、相关资源信息等。
     * 
     * @var array|null
     */
    protected(set) array|null $data = null;

    /**
     * HTTP 响应状态码
     * 
     * 定义返回给客户端的 HTTP 状态码，默认为 400（Bad Request）。
     * 常用状态码：
     * - 400: 请求错误
     * - 401: 未授权
     * - 403: 禁止访问
     * - 404: 资源不存在
     * - 422: 验证错误
     * - 500: 服务器错误
     * 
     * @var int
     */
    protected(set) int $httpStatus = 400 {
        final set {
            if ($value <= 0) {
                throw new RuntimeException('http status must be greater than 0');
            }
            $this->httpStatus = $value;
        }
    }

    /**
     * 是否使用前一个异常的信息
     * 
     * 当设置为 true 时，会从前一个异常（如果存在）继承显示消息、
     * 行为、数据和 HTTP 状态码等属性。这在异常链式传递时特别有用。
     * 
     * @var bool
     */
    protected(set) bool $usePrevious = false;

    /**
     * 是否需要报告该异常
     * 
     * 控制异常是否应该被报告（记录到日志或发送到监控系统）。
     * 某些预期的业务异常（如验证失败）可能不需要报告。
     * 
     * @var bool
     */
    protected(set) bool $reportable = true;

    /**
     * 设置是否使用前一个异常的信息
     * 
     * @param bool $use 是否使用，默认 true
     * @return static 返回自身以支持链式调用
     */
    public function usePrevious(bool $use = true): static
    {
        $this->usePrevious = $use;
        return $this;
    }

    /**
     * 设置前端显示消息
     * 
     * @param string $message 要显示给用户的友好错误消息
     * @return static 返回自身以支持链式调用
     */
    public function withDisplayMessage(string $message): static
    {
        $this->displayMessage = $message;
        return $this;
    }

    /**
     * 设置前端行为
     * 
     * @param array|null $behaviour 前端行为配置数组
     * @return static 返回自身以支持链式调用
     */
    public function withBehaviour(?array $behaviour = null): static
    {
        $this->behaviour = $behaviour;
        return $this;
    }

    /**
     * 设置携带的数据
     * 
     * @param array $data 要传递给前端的额外数据
     * @return static 返回自身以支持链式调用
     */
    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 设置 HTTP 状态码
     * 
     * @param int $httpStatus HTTP 响应状态码
     * @return static 返回自身以支持链式调用
     * @throws RuntimeException 当状态码小于等于 0 时抛出
     */
    public function withHttpStatus(int $httpStatus): static
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    /**
     * 标记异常不需要报告
     * 
     * 调用此方法后，异常将不会被记录到日志或发送到监控系统。
     * 适用于预期的业务异常，如用户输入验证失败。
     * 
     * @return static 返回自身以支持链式调用
     */
    public function dontReport(): static
    {
        $this->reportable = false;
        return $this;
    }

    /**
     * 构造函数
     * 
     * @param string $message 异常消息
     * @param mixed $code 异常代码，会被转换为整数
     * @param Throwable|null $previous 前一个异常，用于异常链
     */
    public function __construct(string $message = "", mixed $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, (int)$code, $previous);
    }


    /**
     * 创建 LunaException 实例
     * 
     * 提供便捷的工厂方法创建异常实例，支持从字符串或其他异常创建。
     * 
     * @param Throwable|string $throwable 异常实例或错误消息字符串
     * @param mixed $code 异常代码，如果不提供则使用原异常的代码
     * @param bool $usePrevious 是否使用前一个异常的信息，默认 true
     * @return static 新的 LunaException 实例
     * 
     * @example
     * // 从字符串创建
     * $exception = LunaException::create('用户未找到', 404);
     * 
     * // 从其他异常创建
     * try {
     *     // some code
     * } catch (\Exception $e) {
     *     throw LunaException::create($e)->withDisplayMessage('操作失败');
     * }
     */
    public static function create(Throwable|string $throwable, mixed $code = null, bool $usePrevious = true): static
    {
        if (is_string($code)) {
            $code = (int)$code;
        }

        if (is_string($throwable)) {
            return new static($throwable, $code ?? 0);
        }

        return new static($throwable->getMessage(), $code ?? $throwable->getCode(),
            $throwable)->usePrevious($usePrevious);
    }

    /**
     * 报告异常
     *
     * 根据配置决定是否报告异常，并使用配置的报告器进行报告。
     * 如果设置了使用前一个异常，则会扩展前一个异常的信息。
     *
     * @param LunaExceptionConfigure $configure 异常配置对象
     * @return bool 是否成功报告异常
     */
    public function report(LunaExceptionConfigure $configure): bool
    {
        try {
            if ($this->usePrevious) {
                $this->extendPreviousException($configure);
            }

            if ($this->reportable) {
                $reporter = $configure->reporter ?? function (Throwable $throwable) {
                    Log::error($throwable->getMessage(), [
                        'exception' => $throwable,
                        'trace' => $throwable->getTraceAsString(),
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ]);
                };

                if ($this->usePrevious && $this->getPrevious()) {
                    $reporter($this->getPrevious());
                } else {
                    $reporter($this);
                }
            }

            return true;
        } catch (Throwable $e) {
            // 确保异常报告本身不会抛出异常
            Log::error('Failed to report Luna exception', [
                'original_exception' => $this->getMessage(),
                'report_error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 扩展前一个异常的信息
     *
     * 根据前一个异常的类型，继承其显示信息、行为、数据和HTTP状态码。
     * 如果前一个异常是 LunaException，则直接继承其属性。
     * 否则，使用异常映射器进行转换。
     *
     * @param LunaExceptionConfigure $configure 异常配置对象
     * @return void
     */
    private function extendPreviousException(LunaExceptionConfigure $configure): void
    {
        $previous = $this->getPrevious();

        if (!$previous) {
            return;
        }

        try {
            if ($previous instanceof LunaException) {
                // 如果前一个异常是 LunaException，直接继承其属性
                if ($previous->displayMessage) {
                    $this->withDisplayMessage($previous->displayMessage);
                }
                if ($previous->behaviour) {
                    $this->withBehaviour($previous->behaviour);
                }
                if ($previous->data) {
                    $this->withData($previous->data);
                }
                $this->withHttpStatus($previous->httpStatus);
                $this->reportable = $previous->reportable;
            } else {
                // 使用异常映射器处理其他类型的异常
                $mapper = $configure->exceptionMappers[$previous::class] ?? null;

                if ($mapper) {
                    $result = $mapper($previous);
                    $this->reportable = $result['report'] ?? true;

                    if (isset($result['data'])) {
                        $this->withData($result['data']);
                    }
                    if (isset($result['message'])) {
                        $this->withDisplayMessage($result['message']);
                    }
                    if (isset($result['behaviour'])) {
                        $this->withBehaviour($result['behaviour']);
                    }
                    $this->withHttpStatus($result['httpStatus'] ?? 500);
                } else {
                    // 没有映射器时的默认处理
                    $this->withDisplayMessage($previous->getMessage());
                    $this->withHttpStatus(500);
                }
            }
        } catch (Throwable $e) {
            // 确保异常扩展过程不会抛出异常
            Log::warning('Failed to extend previous exception', [
                'previous_exception' => $previous->getMessage(),
                'extend_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 渲染异常响应
     *
     * 根据请求类型和配置决定如何渲染异常响应。
     * 支持 JSON 响应和传统的 HTML 响应。
     *
     * @param Request $request HTTP 请求对象
     * @return bool|Response 响应对象或 false（让 Laravel 默认处理）
     */
    public function render(Request $request): bool|Response
    {
        try {
            $configure = app(LunaExceptionConfigure::class);

            if ($this->usePrevious) {
                $this->extendPreviousException($configure);
            }

            // 如果配置为总是返回 JSON 响应
            if ($configure->alwaysJsonRender) {
                return err($this);
            }

            // 根据请求类型决定响应格式
            return $request->expectsJson()
                ? err($this)
                : false; // 返回 false 让 Laravel 使用默认的异常处理
        } catch (Throwable $e) {
            // 确保异常渲染过程不会抛出异常
            Log::error('Failed to render Luna exception', [
                'original_exception' => $this->getMessage(),
                'render_error' => $e->getMessage(),
            ]);
            
            // 返回一个安全的错误响应
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'data' => null,
                'behaviour' => null,
            ], 500);
        }
    }
}