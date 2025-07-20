<?php

namespace Dybasedev\LunaPrototype\Trade\Standard;

use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionNumberGenerator;
use Random\RandomException;

/**
 * 标准交易编号生成器
 * 
 * 生成格式：前缀 + 年月日时分秒(14位) + 交易ID(补零到8位) + 随机数(4位)
 * 例如：T20240101120000000000014567
 * 
 * @package Dybasedev\LunaPrototype\Trade\Standard
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardTransactionNumberGenerator implements TransactionNumberGenerator
{
    /**
     * @var string 编号前缀
     */
    protected string $prefix;
    
    /**
     * @var int ID部分的长度
     */
    protected int $idLength;
    
    /**
     * @var int 随机数部分的长度
     */
    protected int $randomLength;
    
    /**
     * 构造函数
     * 
     * @param string $prefix 编号前缀，默认为 'T'
     * @param int $idLength ID部分的长度，默认为 8
     * @param int $randomLength 随机数部分的长度，默认为 4
     */
    public function __construct(
        string $prefix = 'T',
        int $idLength = 8,
        int $randomLength = 4
    ) {
        $this->prefix = $prefix;
        $this->idLength = $idLength;
        $this->randomLength = $randomLength;
    }
    
    /**
     * @inheritDoc
     */
    public function generate(Transaction $transaction, array $options = []): string
    {
        $timestamp = now()->format('YmdHis'); // 14位时间戳
        $id = str_pad((string)$transaction->getTransactionId(), $this->idLength, '0', STR_PAD_LEFT);
        
        try {
            $randomMax = (int)str_repeat('9', $this->randomLength);
            $randomMin = (int)('1' . str_repeat('0', $this->randomLength - 1));
            $random = random_int($randomMin, $randomMax);
        } catch (RandomException $e) {
            // 如果随机数生成失败，使用时间的微秒部分
            $random = substr(microtime(true) * 10000, -$this->randomLength);
        }
        
        return $this->prefix . $timestamp . $id . $random;
    }
    
    /**
     * @inheritDoc
     */
    public function parseId(string $transactionNumber): int|string|null
    {
        if (!$this->validate($transactionNumber)) {
            return null;
        }
        
        // 跳过前缀
        $withoutPrefix = substr($transactionNumber, strlen($this->prefix));
        
        // 跳过时间戳（14位）
        $withoutTimestamp = substr($withoutPrefix, 14);
        
        // 提取ID部分
        $idPart = substr($withoutTimestamp, 0, $this->idLength);
        
        // 转换为整数（去掉前导零）
        return (int)$idPart;
    }
    
    /**
     * @inheritDoc
     */
    public function validate(string $transactionNumber): bool
    {
        // 检查长度
        $expectedLength = strlen($this->prefix) + 14 + $this->idLength + $this->randomLength;
        if (strlen($transactionNumber) !== $expectedLength) {
            return false;
        }
        
        // 检查前缀
        if (!str_starts_with($transactionNumber, $this->prefix)) {
            return false;
        }
        
        // 检查时间戳部分是否为数字
        $timestampPart = substr($transactionNumber, strlen($this->prefix), 14);
        if (!ctype_digit($timestampPart)) {
            return false;
        }
        
        // 检查ID和随机数部分是否为数字
        $remaining = substr($transactionNumber, strlen($this->prefix) + 14);
        if (!ctype_digit($remaining)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function getFormatDescription(): string
    {
        return sprintf(
            '%s + YYYYMMDDHHmmss(14) + ID(%d) + Random(%d)',
            $this->prefix,
            $this->idLength,
            $this->randomLength
        );
    }
    
    /**
     * 从交易编号中提取时间戳
     * 
     * @param string $transactionNumber
     * @return \DateTimeInterface|null
     */
    public function parseTimestamp(string $transactionNumber): ?\DateTimeInterface
    {
        if (!$this->validate($transactionNumber)) {
            return null;
        }
        
        $timestampPart = substr($transactionNumber, strlen($this->prefix), 14);
        
        try {
            return \DateTime::createFromFormat('YmdHis', $timestampPart);
        } catch (\Exception $e) {
            return null;
        }
    }
}