<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount;

/**
 * 支持单位转换的操作构建器接口
 * 
 * 定义了获取转换上下文信息的方法
 */
interface ConversionAwareOperationBuilder
{
    /**
     * 获取转换上下文信息
     * 
     * @return array|null 转换上下文，包含原始金额、汇率、手续费等信息
     */
    public function getConversionContext(): ?array;
    
    /**
     * 预览操作（不构建）
     * 
     * @return array 操作数组
     */
    public function peekOperations(): array;
    
    /**
     * 更新操作数据
     * 
     * @param array $operations
     * @return void
     */
    public function updateOperations(array $operations): void;
}