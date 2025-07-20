<?php

namespace Dybasedev\LunaPrototype\Trade;

/**
 * 交易编号生成器接口
 * 
 * 定义交易编号的生成和解析规则，允许业务方自定义编号格式。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface TransactionNumberGenerator
{
    /**
     * 生成交易编号
     * 
     * @param Transaction $transaction 交易实例
     * @param array $options 额外选项
     * @return string 生成的交易编号
     */
    public function generate(Transaction $transaction, array $options = []): string;
    
    /**
     * 从交易编号解析出交易ID
     * 
     * @param string $transactionNumber 交易编号
     * @return int|string|null 交易ID，如果无法解析则返回 null
     */
    public function parseId(string $transactionNumber): int|string|null;
    
    /**
     * 验证交易编号格式是否合法
     * 
     * @param string $transactionNumber 交易编号
     * @return bool
     */
    public function validate(string $transactionNumber): bool;
    
    /**
     * 获取编号格式说明
     * 
     * @return string
     */
    public function getFormatDescription(): string;
}