<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

/**
 * 交易状态抽象类
 * 
 * 将状态作为对象处理，支持状态进入和离开的回调处理。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class TransactionStatus
{
    /**
     * 状态标识
     */
    protected(set) string $key {
        get => $this->key;
    }
    
    /**
     * 状态名称
     */
    protected(set) string $name {
        get => $this->name;
    }
    
    /**
     * 状态描述
     */
    protected(set) string $description {
        get => $this->description;
    }
    
    /**
     * 状态码（通过 short_hash_code 生成）
     */
    protected(set) int $code {
        get => $this->code ?? $this->code = short_hash_code($this->key);
    }
    
    public function __construct()
    {
        // Code generation now handled by property hook
    }
    
    /**
     * 状态到达时的处理
     * 
     * @param TradeTransaction $transaction
     * @param TransactionStatus|null $fromStatus
     * @param array $context
     * @return void
     */
    public function onReached(
        TradeTransaction $transaction,
        ?TransactionStatus $fromStatus,
        array $context = []
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 状态离开时的处理
     * 
     * @param TradeTransaction $transaction
     * @param TransactionStatus $toStatus
     * @param array $context
     * @return void
     */
    public function onLeaving(
        TradeTransaction $transaction,
        TransactionStatus $toStatus,
        array $context = []
    ): void {
        // 子类可以重写此方法实现具体逻辑
    }
    
    /**
     * 检查是否可以转换到指定状态
     * 
     * @param TransactionStatus $toStatus
     * @param TradeTransaction $transaction
     * @param array $context
     * @return bool
     */
    public function canTransitionTo(
        TransactionStatus $toStatus,
        TradeTransaction $transaction,
        array $context = []
    ): bool {
        // 子类可以重写此方法实现状态转换规则
        return true;
    }
    
    /**
     * 获取可以转换到的状态列表
     * 
     * @return array<string> 状态标识列表
     */
    public function getAllowedTransitions(): array
    {
        // 子类应该重写此方法返回允许的状态转换
        return [];
    }
    
    /**
     * 检查是否是最终状态
     * 
     * @return bool
     */
    public function isFinal(): bool
    {
        return false;
    }
    
    /**
     * 检查是否是初始状态
     * 
     * @return bool
     */
    public function isInitial(): bool
    {
        return false;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_final' => $this->isFinal(),
            'is_initial' => $this->isInitial(),
            'allowed_transitions' => $this->getAllowedTransitions(),
        ];
    }
}