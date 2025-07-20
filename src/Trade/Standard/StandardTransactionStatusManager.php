<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

use Dybasedev\LunaPrototype\Trade\TransactionStatus;
use Dybasedev\LunaPrototype\Trade\Standard\Status\PendingPaymentStatus;
use Dybasedev\LunaPrototype\Trade\Standard\Status\PaidStatus;
use Dybasedev\LunaPrototype\Trade\Standard\Status\CompletedStatus;
use Dybasedev\LunaPrototype\Trade\Standard\Status\CanceledStatus;
use Dybasedev\LunaPrototype\Trade\Standard\Status\ExpiredStatus;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;

/**
 * 标准交易状态管理器
 * 
 * 管理所有标准交易状态对象的创建和获取。
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardTransactionStatusManager
{
    /**
     * @var array<string, TransactionStatus> 状态实例缓存
     */
    protected array $statusInstances = [];
    
    /**
     * @var array<string, class-string<TransactionStatus>> 状态类映射
     */
    protected array $statusClasses = [
        'pending_payment' => PendingPaymentStatus::class,
        'paid' => PaidStatus::class,
        'completed' => CompletedStatus::class,
        'canceled' => CanceledStatus::class,
        'expired' => ExpiredStatus::class,
    ];
    
    /**
     * @var array<int, string> 状态码到状态键的映射
     */
    protected array $codeToKeyMap = [];
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->initializeCodeMap();
    }
    
    /**
     * 初始化状态码映射
     * 
     * @return void
     */
    protected function initializeCodeMap(): void
    {
        foreach ($this->statusClasses as $key => $class) {
            $status = $this->getStatus($key);
            $this->codeToKeyMap[$status->getCode()] = $key;
        }
    }
    
    /**
     * 根据状态键获取状态对象
     * 
     * @param string $key 状态键
     * @return TransactionStatus
     * @throws LunaException
     */
    public function getStatus(string $key): TransactionStatus
    {
        if (!isset($this->statusInstances[$key])) {
            if (!isset($this->statusClasses[$key])) {
                throw LunaException::create("Unknown transaction status: {$key}")
                    ->withDisplayMessage('未知的交易状态')
                    ->withData(['status_key' => $key])
                    ->withHttpStatus(400);
            }
            
            $class = $this->statusClasses[$key];
            $this->statusInstances[$key] = new $class();
        }
        
        return $this->statusInstances[$key];
    }
    
    /**
     * 根据状态码获取状态对象
     * 
     * @param int $code 状态码
     * @return TransactionStatus
     * @throws LunaException
     */
    public function getStatusByCode(int $code): TransactionStatus
    {
        if (!isset($this->codeToKeyMap[$code])) {
            throw LunaException::create("Unknown transaction status code: {$code}")
                ->withDisplayMessage('未知的交易状态码')
                ->withData(['status_code' => $code])
                ->withHttpStatus(400);
        }
        
        return $this->getStatus($this->codeToKeyMap[$code]);
    }
    
    /**
     * 获取所有状态
     * 
     * @return array<string, TransactionStatus>
     */
    public function getAllStatuses(): array
    {
        $statuses = [];
        foreach ($this->statusClasses as $key => $class) {
            $statuses[$key] = $this->getStatus($key);
        }
        return $statuses;
    }
    
    /**
     * 获取初始状态
     * 
     * @return TransactionStatus
     */
    public function getInitialStatus(): TransactionStatus
    {
        return $this->getStatus('pending_payment');
    }
    
    /**
     * 获取已完成状态
     * 
     * @return TransactionStatus
     */
    public function getCompletedStatus(): TransactionStatus
    {
        return $this->getStatus('completed');
    }
    
    /**
     * 获取已取消状态
     * 
     * @return TransactionStatus
     */
    public function getCanceledStatus(): TransactionStatus
    {
        return $this->getStatus('canceled');
    }
    
    /**
     * 检查状态转换是否有效
     * 
     * @param string|int $fromStatus 起始状态（键或码）
     * @param string|int $toStatus 目标状态（键或码）
     * @return bool
     */
    public function isValidTransition(string|int $fromStatus, string|int $toStatus): bool
    {
        $from = is_string($fromStatus) ? 
            $this->getStatus($fromStatus) : 
            $this->getStatusByCode($fromStatus);
            
        $to = is_string($toStatus) ? 
            $this->getStatus($toStatus) : 
            $this->getStatusByCode($toStatus);
        
        return in_array($to->getKey(), $from->getAllowedTransitions());
    }
    
    /**
     * 获取状态的所有信息
     * 
     * @return array
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->getAllStatuses() as $key => $status) {
            $data[$key] = $status->toArray();
        }
        return $data;
    }
}