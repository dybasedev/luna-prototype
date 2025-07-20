<?php

namespace Dybasedev\LunaPrototype\Trade;

/**
 * 可交易项目
 * 
 * 在交易预览和交易中包装可交易对象，包含数量和其他选项。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TradableItem
{
    /**
     * @var Tradable 可交易对象实例
     */
    protected Tradable $tradable;
    
    /**
     * @var float 数量（支持小数，如重量、长度等）
     */
    protected float $quantity;
    
    /**
     * @var array 附加选项（如规格、颜色、自定义属性等）
     */
    protected array $options;
    
    /**
     * @var float 实际单价（可能已应用折扣）
     */
    protected float $unitPrice;
    
    /**
     * @var float 原始单价（未折扣的价格）
     */
    protected float $originUnitPrice;
    
    /**
     * @var float 实际总金额（单价 × 数量）
     */
    protected float $amount;
    
    /**
     * @var float 原始总金额（原始单价 × 数量）
     */
    protected float $originAmount;
    
    /**
     * 构造函数
     * 
     * @param Tradable $tradable 可交易对象
     * @param float $quantity 数量，默认为 1.0
     * @param array $options 附加选项，可包含：
     *                      - unit_price: 自定义单价（覆盖对象默认价格）
     *                      - origin_unit_price: 自定义原始单价
     *                      - 其他自定义属性（如颜色、规格等）
     */
    public function __construct(Tradable $tradable, float $quantity = 1.0, array $options = [])
    {
        $this->tradable = $tradable;
        $this->quantity = $quantity;
        $this->options = $options;
        
        // 计算价格
        $this->unitPrice = $options['unit_price'] ?? $tradable->getTradablePrice();
        $this->originUnitPrice = $options['origin_unit_price'] ?? $tradable->getTradableOriginPrice();
        
        $this->amount = $this->unitPrice * $this->quantity;
        $this->originAmount = $this->originUnitPrice * $this->quantity;
    }
    
    /**
     * 获取可交易对象
     * 
     * @return Tradable
     */
    public function getTradable(): Tradable
    {
        return $this->tradable;
    }
    
    /**
     * 获取数量
     * 
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }
    
    /**
     * 获取附加选项
     * 
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
    
    /**
     * 获取实际单价
     * 
     * @return float
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }
    
    /**
     * 获取原始单价
     * 
     * @return float
     */
    public function getOriginUnitPrice(): float
    {
        return $this->originUnitPrice;
    }
    
    /**
     * 获取实际总金额
     * 
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }
    
    /**
     * 获取原始总金额
     * 
     * @return float
     */
    public function getOriginAmount(): float
    {
        return $this->originAmount;
    }
    
    /**
     * 更新数量
     * 
     * 更新项目数量并重新计算总金额
     * 
     * @param float $quantity 新的数量
     * @return void
     */
    public function updateQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
        $this->amount = $this->unitPrice * $this->quantity;
        $this->originAmount = $this->originUnitPrice * $this->quantity;
    }
    
    /**
     * 转换为数组
     * 
     * 将交易项目转换为数组格式，便于序列化和展示
     * 
     * @return array{
     *     tradable_id: int|string,
     *     tradable_type: string,
     *     tradable_name: string,
     *     quantity: float,
     *     unit_price: float,
     *     origin_unit_price: float,
     *     amount: float,
     *     origin_amount: float,
     *     options: array
     * }
     */
    public function toArray(): array
    {
        return [
            'tradable_id' => $this->tradable->getTradableId(),
            'tradable_type' => $this->tradable->getTradableType(),
            'tradable_name' => $this->tradable->getTradableName(),
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'origin_unit_price' => $this->originUnitPrice,
            'amount' => $this->amount,
            'origin_amount' => $this->originAmount,
            'options' => $this->options,
        ];
    }
}