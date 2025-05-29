<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Illuminate\Database\Eloquent\Model;

trait WithModelInstance
{
    protected(set) Model|null $modelInstance = null {
        set => $this->modelInstance = $value;
    }

    /**
     * @param mixed $instance
     * @return $this
     */
    public function loadInstance(Model $instance): static
    {
        $this->modelInstance = $instance;
        return $this;
    }
}
