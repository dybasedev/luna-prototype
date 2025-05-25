<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Illuminate\Contracts\Cache\Repository as Cache;

class LunaConfiguration
{
    protected array $groups = [];

    public function __construct(
        protected LunaConfigurationConfigure $configure,
        protected Cache $cache
    ) {
    }

    public function group(string $name): ConfigurationGroup
    {
        if (isset($this->groups[$name])) {
            return $this->groups[$name];
        }

        return $this->groups[$name] = new ConfigurationGroup($this->configure, $name)->withCache($this->cache);
    }
}