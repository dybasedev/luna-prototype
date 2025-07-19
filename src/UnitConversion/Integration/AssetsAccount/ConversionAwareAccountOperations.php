<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\AccountOperations;
use Dybasedev\LunaPrototype\AssetsAccount\AccountOperationBuilder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;

/**
 * 支持单位转换的账户操作类
 * 
 * 扩展了原有的 AccountOperations，支持在账户操作时记录单位转换信息
 */
class ConversionAwareAccountOperations extends AccountOperations
{
    /**
     * 转换相关的元数据键名
     */
    const string CONVERSION_METADATA_KEY = '_unit_conversion';
    
    /**
     * 添加账户操作（增强版）
     * 
     * 支持处理带有单位转换信息的操作构建器
     *
     * @param AccountOperationBuilder $builder 账户操作构建器
     * @return static 当前实例，支持链式调用
     * @throws LunaException 当操作构建失败时抛出
     */
    public function operation(AccountOperationBuilder $builder): static
    {
        // 如果是转换感知的构建器，则提取转换信息
        if ($builder instanceof ConversionAwareOperationBuilder) {
            $this->processConversionAwareBuilder($builder);
        }
        
        return parent::operation($builder);
    }
    
    /**
     * 处理支持转换的操作构建器
     * 
     * @param ConversionAwareOperationBuilder $builder
     * @return void
     */
    protected function processConversionAwareBuilder(ConversionAwareOperationBuilder $builder): void
    {
        // 获取转换上下文
        $conversionContext = $builder->getConversionContext();
        
        if (!$conversionContext) {
            return;
        }
        
        // 将转换信息注入到操作的 payload 中
        $operations = $builder->peekOperations();
        
        foreach ($operations as &$operation) {
            if (!isset($operation['payload'])) {
                $operation['payload'] = [];
            }
            
            // 将转换信息存储在专门的键下，避免与其他 payload 数据冲突
            $operation['payload'][self::CONVERSION_METADATA_KEY] = $conversionContext;
        }
        
        // 更新构建器的操作数据
        $builder->updateOperations($operations);
    }
    
    /**
     * 创建账户变更查询（增强版）
     * 
     * 确保转换信息被正确记录到日志中
     * 
     * @param array $payload 操作载荷
     * @return \Illuminate\Contracts\Database\Query\Builder
     */
    protected function createAccountChangeQueryFromPayload(array $payload): \Illuminate\Contracts\Database\Query\Builder
    {
        // 确保 payload 中的转换信息被正确编码
        if (isset($payload['payload'][self::CONVERSION_METADATA_KEY])) {
            // 转换信息已经在 payload 中，无需额外处理
        }
        
        return parent::createAccountChangeQueryFromPayload($payload);
    }
}