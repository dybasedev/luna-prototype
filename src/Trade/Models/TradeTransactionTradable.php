<?php

namespace Dybasedev\LunaPrototype\Trade\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 交易事务可交易对象关联模型
 * 
 * @property int $id
 * @property int $transaction_id 交易ID
 * @property int $tradable_id 可交易对象ID
 * @property int $tradable_type 可交易对象类型
 * @property float $amount 金额
 * @property float $origin_amount 原始金额
 * @property int|null $unit_id 单位ID
 * @property array $payload 额外数据
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 * 
 * @property-read TradeTransaction $transaction
 * 
 * @package Dybasedev\LunaPrototype\Trade\Models
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TradeTransactionTradable extends Model
{
    protected $table = 'luna_trade_transaction_tradables';
    
    protected $fillable = [
        'transaction_id',
        'tradable_id',
        'tradable_type',
        'amount',
        'origin_amount',
        'unit_id',
        'payload',
    ];
    
    protected $casts = [
        'amount' => 'float',
        'origin_amount' => 'float',
        'payload' => 'array',
    ];
    
    protected $attributes = [
        'payload' => '{}',
    ];
    
    /**
     * 获取关联的交易
     * 
     * @return BelongsTo
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(TradeTransaction::class, 'transaction_id');
    }
    
    /**
     * 获取数量
     * 
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->payload['quantity'] ?? 1.0;
    }
    
    /**
     * 设置数量
     * 
     * @param float $quantity
     * @return void
     */
    public function setQuantity(float $quantity): void
    {
        $payload = $this->payload;
        $payload['quantity'] = $quantity;
        $this->payload = $payload;
    }
}