<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 交易接口
 * 
 * 定义交易对象的基本行为，业务方可以实现此接口来创建自定义的交易模型。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface Transaction
{
    /**
     * 获取交易ID
     * 
     * @return int|string
     */
    public function getTransactionId(): int|string;
    
    /**
     * 获取交易编号
     * 
     * @return string
     */
    public function getTransactionNumber(): string;
    
    /**
     * 设置交易编号
     * 
     * @param string $number
     * @return void
     */
    public function setTransactionNumber(string $number): void;
    
    /**
     * 获取交易所有者
     * 
     * @return SessionHolder|null
     */
    public function getOwner(): ?SessionHolder;
    
    /**
     * 获取交易状态
     * 
     * @return int
     */
    public function getStatus(): int;
    
    /**
     * 设置交易状态
     * 
     * @param int $status
     * @return void
     */
    public function setStatus(int $status): void;
    
    /**
     * 获取交易金额
     * 
     * @return float
     */
    public function getAmount(): float;
    
    /**
     * 获取原始金额
     * 
     * @return float
     */
    public function getOriginAmount(): float;
    
    /**
     * 获取交易处理器ID
     * 
     * @return int
     */
    public function getHandlerId(): int;
    
    /**
     * 获取交易载荷数据
     * 
     * @return array|null
     */
    public function getPayload(): ?array;
    
    /**
     * 设置交易载荷数据
     * 
     * @param array|null $payload
     * @return void
     */
    public function setPayload(?array $payload): void;
    
    /**
     * 获取交易过期时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getExpiredAt(): ?\DateTimeInterface;
    
    /**
     * 获取交易完成时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getCompletedAt(): ?\DateTimeInterface;
    
    /**
     * 设置交易完成时间
     * 
     * @param \DateTimeInterface|null $completedAt
     * @return void
     */
    public function setCompletedAt(?\DateTimeInterface $completedAt): void;
    
    /**
     * 获取交易取消时间
     * 
     * @return \DateTimeInterface|null
     */
    public function getCanceledAt(): ?\DateTimeInterface;
    
    /**
     * 设置交易取消时间
     * 
     * @param \DateTimeInterface|null $canceledAt
     * @return void
     */
    public function setCanceledAt(?\DateTimeInterface $canceledAt): void;
    
    /**
     * 交易是否已完成
     * 
     * @return bool
     */
    public function isFinished(): bool;
    
    /**
     * 设置交易是否已完成
     * 
     * @param bool $finished
     * @return void
     */
    public function setFinished(bool $finished): void;
    
    /**
     * 是否包含多个可交易对象
     * 
     * @return bool
     */
    public function hasMultipleTradables(): bool;
    
    /**
     * 获取关联的可交易对象
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getTradables(): \Illuminate\Support\Collection;
    
    /**
     * 保存交易
     * 
     * @param array $options
     * @return bool
     */
    public function save(array $options = []);
    
    /**
     * 刷新交易数据
     * 
     * @return Transaction
     */
    public function refresh(): Transaction;
    
    /**
     * 检查交易是否可以变更状态
     * 
     * @return bool
     */
    public function canChangeStatus(): bool;
    
    /**
     * 标记交易为完成
     * 
     * @return void
     */
    public function markAsCompleted(): void;
    
    /**
     * 标记交易为取消
     * 
     * @param string|null $reason 取消原因
     * @return void
     */
    public function markAsCanceled(?string $reason = null): void;
    
    /**
     * 标记交易为结束
     * 
     * @return void
     */
    public function markAsFinished(): void;
}