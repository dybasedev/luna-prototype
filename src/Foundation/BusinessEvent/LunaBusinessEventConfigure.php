<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class LunaBusinessEventConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<BusinessEvent>
     */
    protected(set) string $model = BusinessEvent::class;

    /**
     * @var array 分组
     */
    protected(set) array $groups = [];

    public function name(): string
    {
        return 'luna.business-event';
    }

    public function useModel(string $class): static
    {
        $this->model = $class;
        return $this;
    }

    public function group(string $name, ?string $displayName = null): static
    {
        if ($name === 'common') {
            throw new RuntimeException('Group name "common" is reserved');
        }

        $this->groups[hash_code($name)] = [
            'name' => $name,
            'display_name' => $displayName ?? $name,
        ];

        return $this;
    }

    public function register(Container $container): void
    {
        $container->singleton('luna.business-event', function ($app) {
            return new LunaBusinessEvent(
                $app->make(LunaBusinessEventConfigure::class),
                $app->make(LunaHandler::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.business-event', LunaBusinessEvent::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        $container->make(LunaHandlerConfigure::class)->group('business-event', '业务事件', function ($register) {
            $register->handler(DefaultBusinessEventHandler::class);
        });
    }


}