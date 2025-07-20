<?php

namespace Dybasedev\LunaPrototype\Trade;

/**
 * 可被交易的对象接口
 * 
 * 这是 Trade 组件的核心接口之一，定义了一个对象成为可交易对象所需的基本行为。
 * 实现此接口的对象可以被纳入交易流程中，例如产品、服务、虚拟商品等。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface Tradable
{
    /**
     * 获取交易对象的唯一标识符
     * 
     * @return int|string
     */
    public function getTradableId(): int|string;
    
    /**
     * 获取交易对象的类型标识
     * 
     * 用于区分不同类型的可交易对象，例如 'product'、'service' 等
     * 
     * @return string
     */
    public function getTradableType(): string;
    
    /**
     * 获取交易对象的名称
     * 
     * @return string
     */
    public function getTradableName(): string;
    
    /**
     * 获取交易对象的描述
     * 
     * @return string
     */
    public function getTradableDescription(): string;
    
    /**
     * 获取交易对象的单价
     * 
     * @return float
     */
    public function getTradablePrice(): float;
    
    /**
     * 获取交易对象的原始价格
     * 
     * 用于优惠、折扣等场景
     * 
     * @return float
     */
    public function getTradableOriginPrice(): float;
    
    /**
     * 获取交易对象的价格单位
     * 
     * 返回单位标识符，如 'CNY'、'USD' 或自定义单位 ID
     * 如果返回 null，表示使用系统默认单位
     * 
     * @return string|int|null
     */
    public function getTradablePriceUnit(): string|int|null;
    
    /**
     * 检查交易对象是否可用
     * 
     * @return bool
     */
    public function isTradableAvailable(): bool;
    
    /**
     * 检查交易对象的库存是否满足指定数量
     * 
     * @param float $quantity 需要的数量
     * @return bool
     */
    public function checkTradableStock(float $quantity): bool;
    
    /**
     * 获取交易对象的额外信息
     * 
     * 返回一个数组，包含特定业务场景下的额外数据
     * 
     * @return array
     */
    public function getTradablePayload(): array;
    
    /**
     * 获取交易对象的提供者信息
     * 
     * 返回一个包含 provider_id 和 provider_type 的数组
     * 如果返回 null，表示由平台提供
     * 
     * @return array{provider_id: int, provider_type: int}|null
     */
    public function getTradableProvider(): ?array;
}