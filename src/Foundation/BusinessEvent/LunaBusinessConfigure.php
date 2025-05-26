<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;

class LunaBusinessConfigure extends LunaModuleConfigure
{
    protected(set) string $model = BusinessEvent::class;

    public function name(): string
    {
        return 'luna.business-event';
    }

    public function useModel(string $class): static
    {
        $this->model = $class;
        return $this;
    }

}