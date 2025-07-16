<?php

namespace Dybasedev\LunaPrototype\Foundation;

class LunaApplication extends LunaModule
{

    public function __construct(
        protected(set) LunaApplicationConfigure $configure,
    )
    {
    }
}