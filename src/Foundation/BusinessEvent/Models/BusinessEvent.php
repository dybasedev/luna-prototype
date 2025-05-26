<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models;

use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;

class BusinessEvent extends Model
{
    use NamedId, WithModelHandler;

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }
}