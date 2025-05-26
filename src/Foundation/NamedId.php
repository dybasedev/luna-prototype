<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait NamedId
{
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * @return Attribute
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => [
                'name' => $value,
                'id' => hash_code($value),
            ],
        );
    }
}