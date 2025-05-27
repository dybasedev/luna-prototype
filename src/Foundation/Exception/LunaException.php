<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public static function create(Throwable|string $throwable, ?int $code = null): static
    {
        if (is_string($throwable)) {
            return new static($throwable, $code ?? 0);
        }

        return new static($throwable->getMessage(), $code ?? $throwable->getCode(), $throwable);
    }

    public function report(LunaExceptionConfigure $configure): bool
    {
        $previous = $this->getPrevious();

        $report = true;
        if ($previous) {
            $mapper = $configure->exceptionMappers[$previous::class] ?? null;

            if ($mapper) {
                $result = $mapper($previous);
                $report = $result['report'] ?? true;

                $this->withData($result['data'] ?? [])
                    ->withDisplayMessage($result['message'] ?? null)
                    ->withBehaviour($result['behaviour'] ?? null)
                    ->withHttpStatus($result['httpStatus'] ?? 500);
            }
        }

        if ($report) {
            $reporter = $configure->reporter ?? function (Throwable $throwable) {
                Log::error($throwable);
            };

            $reporter($this);
            return true;
        }

        return false;
    }

    public function render(Request $request): bool|Response
    {
        $configure = app(LunaExceptionConfigure::class);

        if ($configure->alwaysJsonRender) {
            return err($this);
        }

        return $request->expectsJson()
            ? err($this)
            : false;
    }
}