<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Illuminate\Database\Eloquent\Model;

interface ModelHandler
{
    /**
     * @param mixed $instance
     * @return $this
     */
    public function loadInstance(Model $instance): static;
}
