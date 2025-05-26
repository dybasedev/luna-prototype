<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait LunaSessionHolder
{
    abstract public function getOperatorTypeName(): string;

    public function getOperatorId(): int
    {
        return $this->getKey();
    }

    public function getOperatorType(): int
    {
        return hash_code($this->getOperatorTypeName());
    }
}