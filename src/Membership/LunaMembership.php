<?php

namespace Dybasedev\LunaPrototype\Membership;

use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * 会员功能管理对象
 *
 * 该模块不提供具体的会员管理，但是提供了会员体系需要的一些扩展支持功能
 */
class LunaMembership extends LunaModule
{
    public function __construct(
        protected(set) LunaMembershipConfigure $configure,
        protected Cache $cache
    )
    {
    }
}