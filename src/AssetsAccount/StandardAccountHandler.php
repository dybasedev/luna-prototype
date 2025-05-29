<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

class StandardAccountHandler extends AccountHandler
{
    public function handlerName(): string
    {
        return '标准账户';
    }

    public function handlerDescription(): string
    {
        return '标准账户处理器，不带任何特殊处理的账户';
    }

}