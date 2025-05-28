<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Closure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler;
use Throwable;

class LunaExceptionConfigure extends LunaModuleConfigure
{
    protected(set) array $exceptionMappers = [];

    protected(set) bool $alwaysJsonRender = false;

    protected(set) ?Closure $reporter = null;

    public function name(): string
    {
        return 'luna.exception';
    }

    public function wrap(string|LunaExceptionMapperBuilder $exceptionClass, string|Closure|null $mapper = null, int $httpStatus = 500): static
    {
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

    public function alwaysJsonRender(bool $alwaysJsonRender = true): static
    {
        $this->alwaysJsonRender = $alwaysJsonRender;
        return $this;
    }

    public function reporter(Closure $reporter): static
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function register(Container $container): void
    {
        $container->afterResolving(Handler::class, function (Handler $handler) {
            $handler->map(Throwable::class, fn($throwable) => LunaException::create($throwable));
        });
    }


}