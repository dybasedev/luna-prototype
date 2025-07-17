<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LunaException extends RuntimeException
{
    /**
     * @var string|mixed 响应至前端的错误信息
     */
    protected(set) ?string $displayMessage = null;

    /**
     * @var array|null 行为列表，前端会根据行为列表进行后续操作，该内容由前端约定
     */
    protected(set) array|null $behaviour = null;

    /**
     * @var array|null 携带的数据
     */
    protected(set) array|null $data = null;

    /**
     * @var int 响应至前端的 http 状态码
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
     * @var bool 是否用用上一次异常的 message 和信息
     */
    protected(set) bool $usePrevious = false;

    /**
     * @var bool 是否进行报告
     */
    protected(set) bool $reportable = true;

    public function usePrevious(bool $use = true): static
    {
        $this->usePrevious = $use;
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function withDisplayMessage(string $message): static
    {
        $this->displayMessage = $message;
        return $this;
    }

    /**
     * @param array|null $behaviour
     * @return $this
     */
    public function withBehaviour(?array $behaviour = null): static
    {
        $this->behaviour = $behaviour;
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param int $httpStatus
     * @return $this
     */
    public function withHttpStatus(int $httpStatus): static
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    public function dontReport(): static
    {
        $this->reportable = false;
        return $this;
    }

    public function __construct(string $message = "", mixed $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, (int)$code, $previous);
    }


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