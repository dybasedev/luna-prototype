<?php

namespace Dybasedev\LunaPrototype\Trade\Models;

use Dybasedev\LunaPrototype\Trade\Tradable;
use Illuminate\Database\Eloquent\Model;

/**
 * 标准可交易对象模型
 * 
 * 这是一个实现了 Tradable 接口的标准模型，可以直接用于简单的交易场景。
 * 对于复杂的业务场景，建议创建自定义模型并实现 Tradable 接口。
 * 
 * @property int $id
 * @property int $parent_id 父对象ID
 * @property int|null $provider_id 供应商ID
 * @property int|null $provider_type 供应商类型
 * @property string $code 对象代码
 * @property string|null $name 对象名称
 * @property string $title 标题
 * @property string $summary 概要
 * @property string $description 描述
 * @property int $handler_id 处理器ID
 * @property array $config 处理器配置
 * @property array $payload 额外载荷
 * @property float $amount 金额
 * @property float $origin_amount 原始金额
 * @property int|null $unit_id 单位ID
 * @property float $stock 库存
 * @property int|null $stock_unit_id 库存单位ID
 * @property int $sort 排序
 * @property int $status 状态
 * @property bool $is_enabled 是否启用
 * @property bool $is_display 是否显示
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 * 
 * @package Dybasedev\LunaPrototype\Trade\Models
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TradeTradable extends Model implements Tradable
{
    protected $table = 'luna_trade_tradables';
    
    protected $fillable = [
        'parent_id',
        'provider_id',
        'provider_type',
        'code',
        'name',
        'title',
        'summary',
        'description',
        'handler_id',
        'config',
        'payload',
        'amount',
        'origin_amount',
        'unit_id',
        'stock',
        'stock_unit_id',
        'sort',
        'status',
        'is_enabled',
        'is_display',
    ];
    
    protected $casts = [
        'config' => 'array',
        'payload' => 'array',
        'amount' => 'float',
        'origin_amount' => 'float',
        'stock' => 'float',
        'is_enabled' => 'boolean',
        'is_display' => 'boolean',
    ];
    
    protected $attributes = [
        'parent_id' => 0,
        'title' => '',
        'summary' => '',
        'description' => '',
        'config' => '{}',
        'payload' => '{}',
        'stock' => 0,
        'sort' => 0,
        'is_enabled' => true,
        'is_display' => true,
    ];
    
    /**
     * @inheritDoc
     */
    public function getTradableId(): int|string
    {
        return $this->id;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradableType(): string
    {
        return 'trade_tradable';
    }
    
    /**
     * @inheritDoc
     */
    public function getTradableName(): string
    {
        return $this->title ?: $this->name ?: $this->code;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradableDescription(): string
    {
        return $this->description ?: $this->summary;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradablePrice(): float
    {
        return $this->amount;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradableOriginPrice(): float
    {
        return $this->origin_amount;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradablePriceUnit(): string|int|null
    {
        return $this->unit_id;
    }
    
    /**
     * @inheritDoc
     */
    public function isTradableAvailable(): bool
    {
        return $this->is_enabled && $this->status > 0;
    }
    
    /**
     * @inheritDoc
     */
    public function checkTradableStock(float $quantity): bool
    {
        // 如果stock_unit_id为null，表示不限制库存
        if ($this->stock_unit_id === null) {
            return true;
        }
        
        return $this->stock >= $quantity;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradablePayload(): array
    {
        return $this->payload;
    }
    
    /**
     * @inheritDoc
     */
    public function getTradableProvider(): ?array
    {
        if ($this->provider_id === null) {
            return null;
        }
        
        return [
            'provider_id' => $this->provider_id,
            'provider_type' => $this->provider_type,
        ];
    }
    
    /**
     * 减少库存
     * 
     * @param float $quantity
     * @return bool
     */
    public function decreaseStock(float $quantity): bool
    {
        if (!$this->checkTradableStock($quantity)) {
            return false;
        }
        
        $this->stock -= $quantity;
        return $this->save();
    }
    
    /**
     * 增加库存
     * 
     * @param float $quantity
     * @return bool
     */
    public function increaseStock(float $quantity): bool
    {
        $this->stock += $quantity;
        return $this->save();
    }
}