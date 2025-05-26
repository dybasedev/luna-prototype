<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;

class Handler extends Model
{
    use NamedId;

    protected $table = 'luna_handlers';

    protected function casts():array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }
}