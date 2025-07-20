<?php

namespace Dybasedev\LunaPrototype\Trade\Models;

use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * 交易事务模型
 * 
 * @property int $id
 * @property int|null $provider_id 供应商ID
 * @property int|null $provider_type 供应商类型
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property int $handler_id 处理器ID
 * @property int $parent_id 父交易ID
 * @property int|null $special_mark 特殊标记
 * @property int|null $tradable_id 可交易对象ID
 * @property int|null $tradable_type 可交易对象类型
 * @property float $quantity 数量
 * @property bool $multi_tradables 是否多个交易对象
 * @property int $status 状态
 * @property float $amount 金额
 * @property float $origin_amount 原始金额
 * @property int|null $unit_id 单位ID
 * @property array $payload 额外数据
 * @property bool $is_completed 是否完成
 * @property bool $is_finished 是否结束
 * @property \DateTimeInterface|null $expired_at 过期时间
 * @property \DateTimeInterface|null $completed_at 完成时间
 * @property \DateTimeInterface|null $canceled_at 取消时间
 * @property \DateTimeInterface|null $finished_at 结束时间
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 * 
 * @property-read \Illuminate\Database\Eloquent\Collection<TradeTransactionTradable> $tradables
 * 
 * @package Dybasedev\LunaPrototype\Trade\Models
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TradeTransaction extends Model implements Transaction
{
    protected $table = 'luna_trade_transactions';
    
    protected $fillable = [
        'provider_id',
        'provider_type',
        'owner_id',
        'owner_type',
        'handler_id',
        'parent_id',
        'special_mark',
        'tradable_id',
        'tradable_type',
        'quantity',
        'multi_tradables',
        'status',
        'amount',
        'origin_amount',
        'unit_id',
        'payload',
        'is_completed',
        'is_finished',
        'expired_at',
        'completed_at',
        'canceled_at',
        'finished_at',
    ];
    
    protected $casts = [
        'quantity' => 'float',
        'multi_tradables' => 'boolean',
        'amount' => 'float',
        'origin_amount' => 'float',
        'payload' => 'array',
        'is_completed' => 'boolean',
        'is_finished' => 'boolean',
        'expired_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
    
    protected $attributes = [
        'parent_id' => 0,
        'quantity' => 1,
        'multi_tradables' => false,
        'is_completed' => false,
        'is_finished' => false,
        'payload' => '{}',
    ];
    
    /**
     * 获取交易关联的可交易对象
     * 
     * @return HasMany
     */
    public function tradables(): HasMany
    {
        return $this->hasMany(TradeTransactionTradable::class, 'transaction_id');
    }
    
    /**
     * 获取交易编号
     * 
     * @return string
     */
    public function getTransactionNumber(): string
    {
        return $this->payload['transaction_number'] ?? '';
    }
    
    /**
     * 设置交易编号
     * 
     * @param string $number
     * @return void
     */
    public function setTransactionNumber(string $number): void
    {
        $payload = $this->payload;
        $payload['transaction_number'] = $number;
        $this->payload = $payload;
    }
    
    /**
     * 检查交易是否已过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expired_at === null) {
            return false;
        }
        
        return $this->expired_at->isPast();
    }
    
    /**
     * 检查交易是否可以变更状态
     * 
     * @return bool
     */
    public function canChangeStatus(): bool
    {
        return !$this->is_finished && !$this->is_completed;
    }
    
    /**
     * 标记交易为完成
     * 
     * @return void
     */
    public function markAsCompleted(): void
    {
        $this->is_completed = true;
        $this->completed_at = now();
    }
    
    /**
     * 标记交易为结束
     * 
     * @return void
     */
    public function markAsFinished(): void
    {
        $this->is_finished = true;
        $this->finished_at = now();
    }
    
    /**
     * 标记交易为取消
     * 
     * @param string|null $reason
     * @return void
     */
    public function markAsCanceled(?string $reason = null): void
    {
        $this->canceled_at = now();
        
        if ($reason !== null) {
            $payload = $this->payload;
            $payload['cancel_reason'] = $reason;
            $this->payload = $payload;
        }
    }
    
    /**
     * 获取交易ID
     * 
     * @return int|string
     */
    public function getTransactionId(): int|string
    {
        return $this->id;
    }
    
    /**
     * 获取交易所有者
     * 
     * @return SessionHolder|null
     */
    public function getOwner(): ?SessionHolder
    {
        // 简单返回 null，业务方可以重写此方法实现具体逻辑
        return null;
    }
    
    /**
     * 获取交易状态
     * 
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }
    
    /**
     * 设置交易状态
     * 
     * @param int $status
     * @return void
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }
    
    /**
     * 获取交易金额
     * 
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }
    
    /**
     * 获取原始金额
     * 
     * @return float
     */
    public function getOriginAmount(): float
    {
        return $this->origin_amount;
    }
    
    /**
     * 获取交易处理器ID
     * 
     * @return int
     */
    public function getHandlerId(): int
    {
        return $this->handler_id;
    }
    
    /**
     * 获取交易载荷数据
     * 
     * @return array|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }
    
    /**
     * 设置交易载荷数据
     * 
     * @param array|null $payload
     * @return void
     */
    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }
    
    /**
     * 获取交易过期时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getExpiredAt(): ?\DateTimeInterface
    {
        return $this->expired_at;
    }
    
    /**
     * 获取交易完成时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completed_at;
    }
    
    /**
     * 设置交易完成时间
     * 
     * @param \DateTimeInterface|null $completedAt
     * @return void
     */
    public function setCompletedAt(?\DateTimeInterface $completedAt): void
    {
        $this->completed_at = $completedAt;
    }
    
    /**
     * 获取交易取消时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getCanceledAt(): ?\DateTimeInterface
    {
        return $this->canceled_at;
    }
    
    /**
     * 设置交易取消时间
     * 
     * @param \DateTimeInterface|null $canceledAt
     * @return void
     */
    public function setCanceledAt(?\DateTimeInterface $canceledAt): void
    {
        $this->canceled_at = $canceledAt;
    }
    
    /**
     * 交易是否已完成
     * 
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->is_finished;
    }
    
    /**
     * 设置交易是否已完成
     * 
     * @param bool $finished
     * @return void
     */
    public function setFinished(bool $finished): void
    {
        $this->is_finished = $finished;
    }
    
    /**
     * 是否包含多个可交易对象
     * 
     * @return bool
     */
    public function hasMultipleTradables(): bool
    {
        return $this->multi_tradables;
    }
    
    /**
     * 获取关联的可交易对象
     * 
     * @return Collection
     */
    public function getTradables(): Collection
    {
        return $this->tradables;
    }
    
    /**
     * 刷新交易数据
     * 
     * @return Transaction
     */
    public function refresh(): Transaction
    {
        parent::refresh();
        return $this;
    }
}