<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

use Closure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;

class LunaExceptionConfigure extends LunaModuleConfigure
{
    protected(set) array $exceptionMappers = [];

    protected(set) bool $alwaysJsonRender = false;

    protected(set) ?Closure $reporter = null;

    public function name(): string
    {
        return 'luna.exception';
    }

    public function wrap(string $exceptionClass, string|Closure $mapper, int $httpStatus = 500): static
    {
        if (is_string($mapper)) {
            $mapper = function ($exception) use ($httpStatus, $mapper) {
                return [
                    'message' => $mapper,
                    'httpStatus' => $httpStatus,
                    'report' => true,
                    'behaviour' => null,
                    'data' => null,
                ];
            };
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
}