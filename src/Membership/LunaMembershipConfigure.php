<?php

namespace Dybasedev\LunaPrototype\Membership;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;

class LunaMembershipConfigure extends LunaModuleConfigure
{
    /**
     * @var MembershipBinding[]
     */
    protected(set) array $bindings = [];

    public function name(): string
    {
        return 'luna.membership';
    }

    /**
     * @param MembershipBinding $binding
     * @return $this
     */
    public function bind(MembershipBinding $binding): static
    {
        $this->bindings[] = $binding;
        return $this;
    }

}