<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Closure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use RuntimeException;

class HandlerConfigure extends LunaModuleConfigure
{
    protected(set) array $groups = [];

    protected(set) array $handlers = [];

    public function name(): string
    {
        return 'luna.handler';
    }

    public function handler(string $group, string $name, string $handlerClass): static
    {
        if (!isset($this->groups[$group])) {
            throw new RuntimeException('Handler group not exists.');
        }

        $this->groups[$group]['handlers'][$name] = $handlerClass;
        return $this;
    }

    public function group(string $name, ?string $displayName = null, ?Closure $handlerRegister = null): static
    {
        $this->groups[$name] = [
            'display_name' => $displayName,
        ];

        $handlerAppender = function (string $name, string $handlerClass) {
            $this->groups[$name]['handlers'][$name] = $handlerClass;
        };

        if ($handlerRegister) {
            $handlerRegister(
                new class($this, $handlerAppender) {
                    public function __construct(protected HandlerConfigure $configure, protected Closure $handlerAppender)
                    {
                    }

                    public function handler(string $name, string $handlerClass): static
                    {
                        if (!class_exists($handlerClass)) {
                            throw new RuntimeException('Handler class not exists.');
                        }

                        ($this->handlerAppender)($name, $handlerClass);
                        return $this;
                    }
                }
            );
        }

        return $this;
    }

}