<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Closure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class LunaHandlerConfigure extends LunaModuleConfigure
{
    /**
     * @var array
     */
    protected(set) array $groups = [];

    /**
     * @var array
     */
    protected(set) array $handlers = [];

    /**
     * @var class-string<Models\Handler>
     */
    protected(set) string $model = Models\Handler::class;

    public function name(): string
    {
        return 'luna.handler';
    }

    public function handler(string $group, string $handlerClass): static
    {
        if (!isset($this->groups[hash_code($group)])) {
            throw new RuntimeException('Handler group not exists.');
        }

        $this->groups[hash_code($group)]['handlers'][] = $handlerClass;

        if (!in_array($handlerClass, $this->handlers)) {
            $this->handlers[] = $handlerClass;
        }

        return $this;
    }

    public function group(string $name, ?string $displayName = null, ?Closure $handlerRegister = null): static
    {
        $this->groups[hash_code($name)] = [
            'name' => $name,
            'display_name' => $displayName,
        ];

        $handlerAppender = function (string $handlerClass) use ($name) {
            $this->handler($name, $handlerClass);
        };

        if ($handlerRegister) {
            $handlerRegister(
                new class($this, $handlerAppender) {
                    public function __construct(protected LunaHandlerConfigure $configure, protected Closure $handlerAppender)
                    {
                    }

                    public function handler(string $handlerClass): static
                    {
                        if (!class_exists($handlerClass)) {
                            throw new RuntimeException('Handler class not exists.');
                        }

                        ($this->handlerAppender)($handlerClass);
                        return $this;
                    }
                }
            );
        }

        return $this;
    }

    public function useModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Add a handler group (alias for group method)
     *
     * @param string $name Group name
     * @param string|null $displayName Display name
     * @return $this
     */
    public function addGroup(string $name, ?string $displayName = null): static
    {
        return $this->group($name, $displayName);
    }

    /**
     * Add a handler to the list
     *
     * @param string $handlerClass Handler class name
     * @return $this
     */
    public function addHandler(string $handlerClass): static
    {
        if (!in_array($handlerClass, $this->handlers)) {
            $this->handlers[] = $handlerClass;
        }
        return $this;
    }

    public function register(Container $container): void
    {
        $container->singleton('luna.handler', function ($app) {
            return new LunaHandler(
                $app->make(LunaHandlerConfigure::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.handler', LunaHandler::class);
    }

}