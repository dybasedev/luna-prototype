<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Illuminate\Contracts\Container\Container;

class LunaApplicationConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<Installation>[]
     */
    protected(set) array $installations = [];

    public function name(): string
    {
        return 'luna.app';
    }

    public function installation(string $installation): static
    {
        $this->installations[] = $installation;
        return $this;
    }

}