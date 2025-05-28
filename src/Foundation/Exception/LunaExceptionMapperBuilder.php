<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

/**
 * @template TClass of Throwable
 */
class LunaExceptionMapperBuilder
{
    protected(set) array $body = [];

    /**
     * @var class-string<TClass>
     */
    protected(set) string $exceptionClass;

    /**
     * @param class-string<TClass> $exceptionClass
     */
    public function __construct(string $exceptionClass)
    {
        $this->exceptionClass = $exceptionClass;
    }

    /**
     * @param string|Closure(TClass): string $message
     * @return $this
     */
    public function message(string|Closure $message): static
    {
        $this->body['message'] = $message;
        return $this;
    }

    /**
     * @param int|Closure(TClass): int $httpStatus
     * @return $this
     */
    public function httpStatus(int|Closure $httpStatus): static
    {
        $this->body['httpStatus'] = $httpStatus;
        return $this;
    }

    /**
     * @param bool|Closure(TClass): bool $reportable
     * @return $this
     */
    public function reportable(bool|Closure $reportable): static
    {
        $this->body['reportable'] = $reportable;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontReport(): static
    {
        $this->reportable(false);
        return $this;
    }

    /**
     * @param string|array|Closure(TClass): (string|array|null)|null $behaviour
     * @return $this
     */
    public function behaviour(string|array|Closure|null $behaviour): static
    {
        $this->body['behaviour'] = $behaviour;
        return $this;
    }

    /**
     * @param array|Closure(TClass): (array|null)|null $data
     * @return $this
     */
    public function data(array|Closure|null $data): static
    {
        $this->body['data'] = $data;
        return $this;
    }

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