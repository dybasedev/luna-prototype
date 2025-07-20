<?php

use Dybasedev\LunaPrototype\Trade\LunaTrade;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeOperationBuilder;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeQueryBuilder;

/**
 * 获取交易管理实例
 * 
 * @return LunaTrade|null
 */
function luna_trade(): ?LunaTrade
{
    try {
        return app('luna.trade');
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * 创建标准交易操作构建器
 * 
 * @return StandardTradeOperationBuilder
 */
function luna_trade_operation(): StandardTradeOperationBuilder
{
    return new StandardTradeOperationBuilder();
}

/**
 * 创建标准交易查询构建器
 * 
 * @return StandardTradeQueryBuilder
 */
function luna_trade_query(): StandardTradeQueryBuilder
{
    return new StandardTradeQueryBuilder();
}

/**
 * 快速创建单个可交易对象的交易
 * 
 * @param \Dybasedev\LunaPrototype\Foundation\SessionHolder $owner
 * @param string|int $handlerName
 * @param \Dybasedev\LunaPrototype\Trade\Tradable $tradable
 * @param float $quantity
 * @param array $options
 * @return \Dybasedev\LunaPrototype\Trade\Models\TradeTransaction|null
 */
function luna_create_trade(
    \Dybasedev\LunaPrototype\Foundation\SessionHolder $owner,
    string|int $handlerName,
    \Dybasedev\LunaPrototype\Trade\Tradable $tradable,
    float $quantity = 1.0,
    array $options = []
): ?\Dybasedev\LunaPrototype\Trade\Models\TradeTransaction {
    $trade = luna_trade();
    if (!$trade) {
        return null;
    }
    
    $options['quantity'] = $quantity;
    
    try {
        return $trade->createTransaction($owner, $handlerName, $tradable, $options);
    } catch (Throwable $e) {
        report($e);
        return null;
    }
}