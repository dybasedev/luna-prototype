<?php

namespace Dybasedev\LunaPrototype\Trade\Examples;

use Dybasedev\LunaPrototype\Trade\Transaction;
use Dybasedev\LunaPrototype\Trade\TransactionNumberGenerator;

/**
 * 自定义交易编号生成器示例
 * 
 * 生成格式：前缀 + 年份(4位) + 月日(4位) + 业务类型(2位) + 交易ID(6位) + 校验码(2位)
 * 例如：ORD2024010101000001XX
 * 
 * 这个示例展示了如何：
 * 1. 实现自定义的编号格式
 * 2. 添加校验码防止伪造
 * 3. 包含业务类型信息
 * 4. 支持反向解析
 * 
 * @package Dybasedev\LunaPrototype\Trade\Examples
 */
class CustomTransactionNumberGenerator implements TransactionNumberGenerator
{
    /**
     * @var string 编号前缀
     */
    protected string $prefix;
    
    /**
     * @var array<string, string> 业务类型映射
     */
    protected array $businessTypes = [
        'standard' => '01',
        'refund' => '02',
        'exchange' => '03',
        'virtual' => '04',
    ];
    
    /**
     * 构造函数
     * 
     * @param string $prefix 编号前缀
     */
    public function __construct(string $prefix = 'ORD')
    {
        $this->prefix = $prefix;
    }
    
    /**
     * @inheritDoc
     */
    public function generate(Transaction $transaction, array $options = []): string
    {
        $year = now()->format('Y');
        $monthDay = now()->format('md');
        
        // 获取业务类型代码
        $businessType = $options['business_type'] ?? 'standard';
        $typeCode = $this->businessTypes[$businessType] ?? '00';
        
        // 格式化交易ID为6位
        $transactionId = str_pad((string)$transaction->getTransactionId(), 6, '0', STR_PAD_LEFT);
        
        // 生成基础编号（不含校验码）
        $baseNumber = $this->prefix . $year . $monthDay . $typeCode . $transactionId;
        
        // 计算校验码
        $checksum = $this->calculateChecksum($baseNumber);
        
        return $baseNumber . $checksum;
    }
    
    /**
     * @inheritDoc
     */
    public function parseId(string $transactionNumber): int|string|null
    {
        if (!$this->validate($transactionNumber)) {
            return null;
        }
        
        // 跳过前缀、年份、月日、业务类型
        $offset = strlen($this->prefix) + 4 + 4 + 2;
        
        // 提取6位交易ID
        $idPart = substr($transactionNumber, $offset, 6);
        
        return (int)$idPart;
    }
    
    /**
     * @inheritDoc
     */
    public function validate(string $transactionNumber): bool
    {
        // 检查长度（前缀 + 年份4 + 月日4 + 类型2 + ID6 + 校验码2）
        $expectedLength = strlen($this->prefix) + 4 + 4 + 2 + 6 + 2;
        if (strlen($transactionNumber) !== $expectedLength) {
            return false;
        }
        
        // 检查前缀
        if (!str_starts_with($transactionNumber, $this->prefix)) {
            return false;
        }
        
        // 验证校验码
        $baseNumber = substr($transactionNumber, 0, -2);
        $checksum = substr($transactionNumber, -2);
        
        return $this->calculateChecksum($baseNumber) === $checksum;
    }
    
    /**
     * @inheritDoc
     */
    public function getFormatDescription(): string
    {
        return sprintf(
            '%s + YYYY(4) + MMDD(4) + BusinessType(2) + ID(6) + Checksum(2)',
            $this->prefix
        );
    }
    
    /**
     * 解析交易编号的详细信息
     * 
     * @param string $transactionNumber
     * @return array|null
     */
    public function parseDetails(string $transactionNumber): ?array
    {
        if (!$this->validate($transactionNumber)) {
            return null;
        }
        
        $offset = strlen($this->prefix);
        
        // 解析各部分
        $year = substr($transactionNumber, $offset, 4);
        $monthDay = substr($transactionNumber, $offset + 4, 4);
        $typeCode = substr($transactionNumber, $offset + 8, 2);
        $transactionId = (int)substr($transactionNumber, $offset + 10, 6);
        
        // 查找业务类型名称
        $businessType = array_search($typeCode, $this->businessTypes, true) ?: 'unknown';
        
        return [
            'id' => $transactionId,
            'year' => $year,
            'month' => substr($monthDay, 0, 2),
            'day' => substr($monthDay, 2, 2),
            'business_type' => $businessType,
            'business_type_code' => $typeCode,
            'timestamp' => $year . '-' . substr($monthDay, 0, 2) . '-' . substr($monthDay, 2, 2),
        ];
    }
    
    /**
     * 计算校验码
     * 
     * 使用简单的模运算生成2位校验码
     * 实际应用中可以使用更复杂的算法
     * 
     * @param string $baseNumber
     * @return string
     */
    protected function calculateChecksum(string $baseNumber): string
    {
        $sum = 0;
        $chars = str_split($baseNumber);
        
        foreach ($chars as $i => $char) {
            if (is_numeric($char)) {
                $sum += (int)$char * ($i + 1);
            } else {
                $sum += ord($char) * ($i + 1);
            }
        }
        
        return str_pad((string)($sum % 97), 2, '0', STR_PAD_LEFT);
    }
    
    /**
     * 添加新的业务类型
     * 
     * @param string $name 业务类型名称
     * @param string $code 业务类型代码（2位）
     * @return void
     */
    public function addBusinessType(string $name, string $code): void
    {
        if (strlen($code) !== 2) {
            throw new \InvalidArgumentException('Business type code must be 2 characters');
        }
        
        $this->businessTypes[$name] = $code;
    }
}