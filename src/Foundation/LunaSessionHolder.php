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

    public function getSessionHolderContext(): ?array
    {
        // 默认不提供任何上下文信息，业务端可根据需要对其覆盖

        return [];
    }
}