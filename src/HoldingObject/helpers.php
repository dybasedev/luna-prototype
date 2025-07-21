<?php

use Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObject;

if (!function_exists('luna_holding_object')) {
    /**
     * 获取持有对象组件实例
     *
     * @return LunaHoldingObject
     */
    function luna_holding_object(): LunaHoldingObject
    {
        return app(LunaHoldingObject::class);
    }
}

if (!function_exists('unique_holding_params')) {
    /**
     * 创建唯一对象持有参数构造器
     * 
     * @return \Dybasedev\LunaPrototype\HoldingObject\UniqueHoldingParams
     */
    function unique_holding_params(): \Dybasedev\LunaPrototype\HoldingObject\UniqueHoldingParams
    {
        return \Dybasedev\LunaPrototype\HoldingObject\UniqueHoldingParams::create();
    }
}

if (!function_exists('holding_query')) {
    /**
     * 创建持有对象查询参数构造器
     * 
     * @return \Dybasedev\LunaPrototype\HoldingObject\HoldingQueryParams
     */
    function holding_query(): \Dybasedev\LunaPrototype\HoldingObject\HoldingQueryParams
    {
        return \Dybasedev\LunaPrototype\HoldingObject\HoldingQueryParams::create();
    }
}

if (!function_exists('holding_batch')) {
    /**
     * 创建批量操作参数构造器
     * 
     * @return \Dybasedev\LunaPrototype\HoldingObject\BatchOperationParams
     */
    function holding_batch(): \Dybasedev\LunaPrototype\HoldingObject\BatchOperationParams
    {
        return \Dybasedev\LunaPrototype\HoldingObject\BatchOperationParams::create();
    }
}