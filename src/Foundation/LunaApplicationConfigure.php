<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Illuminate\Contracts\Container\Container;

class LunaApplicationConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<Installation>[]
     */
    protected(set) array $installations = [];

    /**
     * @var class-string<Backupable>[]
     */
    protected(set) array $backupableObjects = [];

    public function name(): string
    {
        return 'luna.app';
    }

    public function installation(string $installation): static
    {
        $this->installations[] = $installation;
        return $this;
    }

    public function register(Container $container): void
    {
        $container->singleton('luna', function ($app) {
            return new LunaApplication(
                $app->make(LunaApplicationConfigure::class),
            );
        });

        $container->alias('luna', LunaApplication::class);
        $container->alias('luna', 'luna.app');
    }


}