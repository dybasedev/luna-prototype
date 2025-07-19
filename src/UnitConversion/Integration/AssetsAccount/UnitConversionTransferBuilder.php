<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\AccountTransferOperationBuilder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;

/**
 * 单位转换转账操作构建器
 * 
 * 支持在不同单位的账户间进行转账，自动处理汇率转换和手续费
 */
class UnitConversionTransferBuilder extends AccountTransferOperationBuilder implements ConversionAwareOperationBuilder
{
    /**
     * 单位转换实例
     */
    protected ?LunaUnitConversion $unitConversion = null;
    
    /**
     * 转换上下文
     */
    protected ?ConversionContext $conversionContext = null;
    
    /**
     * 转换结果
     */
    protected ?ConversionResult $conversionResult = null;
    
    /**
     * 源账户单位
     */
    protected ?string $fromUnit = null;
    
    /**
     * 目标账户单位
     */
    protected ?string $toUnit = null;
    
    /**
     * 手续费扣除方
     */
    protected string $feeDeductFrom = 'from'; // 'from' 或 'to'
    
    /**
     * 是否已执行转换
     */
    protected bool $conversionExecuted = false;
    
    /**
     * 设置单位转换实例
     */
    public function withUnitConversion(LunaUnitConversion $unitConversion): static
    {
        $this->unitConversion = $unitConversion;
        return $this;
    }
    
    /**
     * 设置源账户单位
     */
    public function fromUnit(string $unit): static
    {
        $this->fromUnit = $unit;
        return $this;
    }
    
    /**
     * 设置目标账户单位
     */
    public function toUnit(string $unit): static
    {
        $this->toUnit = $unit;
        return $this;
    }
    
    /**
     * 设置转换上下文
     */
    public function withConversionContext(ConversionContext $context): static
    {
        $this->conversionContext = $context;
        return $this;
    }
    
    /**
     * 设置手续费从转出方扣除
     */
    public function feeFromSender(): static
    {
        $this->feeDeductFrom = 'from';
        return $this;
    }
    
    /**
     * 设置手续费从转入方扣除
     */
    public function feeFromReceiver(): static
    {
        $this->feeDeductFrom = 'to';
        return $this;
    }
    
    /**
     * 获取转换上下文信息
     */
    public function getConversionContext(): ?array
    {
        if (!$this->conversionResult) {
            return null;
        }
        
        return [
            'from_unit' => $this->fromUnit,
            'to_unit' => $this->toUnit,
            'original_amount' => $this->conversionResult->getFromAmount(),
            'converted_amount' => $this->conversionResult->getToAmount(),
            'rate' => $this->conversionResult->getRate(),
            'fee' => $this->conversionResult->getFee(),
            'fee_deduct_from' => $this->feeDeductFrom,
            'conversion_time' => now()->toIso8601String(),
            'context_params' => $this->conversionContext?->getParameters() ?? [],
        ];
    }
    
    /**
     * 预览操作
     */
    public function peekOperations(): array
    {
        // 确保已经设置了必要的参数
        $this->validateParameters();
        
        // 执行转换计算
        $this->executeConversion();
        
        // 构建操作但不返回
        $tempOperation = $this->operation;
        $operations = $this->buildOperations();
        $this->operation = $tempOperation;
        
        return $operations;
    }
    
    /**
     * 更新操作数据
     */
    public function updateOperations(array $operations): void
    {
        // 这个方法主要用于 ConversionAwareAccountOperations 更新 payload
        // 由于我们使用的是 build() 方法构建最终操作，所以这里不需要实现
    }
    
    /**
     * 构建操作
     */
    public function build(): array
    {
        // 验证参数
        $this->validateParameters();
        
        // 执行转换
        $this->executeConversion();
        
        // 构建操作
        return $this->buildOperations();
    }
    
    /**
     * 验证参数
     */
    protected function validateParameters(): void
    {
        // 检查基础参数
        if (!isset($this->operation['amount'])) {
            throw LunaException::create('未设置转账金额');
        }
        
        // 如果设置了单位，确保单位转换实例可用
        if (($this->fromUnit || $this->toUnit) && !$this->unitConversion) {
            // 尝试从容器获取
            $this->unitConversion = luna_unit_conversion();
            if (!$this->unitConversion) {
                throw LunaException::create('单位转换模块不可用');
            }
        }
    }
    
    /**
     * 执行转换计算
     */
    protected function executeConversion(): void
    {
        // 如果已经执行过转换，直接返回
        if ($this->conversionExecuted) {
            return;
        }
        
        // 如果没有设置单位或单位相同，不需要转换
        if (!$this->fromUnit || !$this->toUnit || $this->fromUnit === $this->toUnit) {
            $this->conversionExecuted = true;
            return;
        }
        
        // 创建默认上下文
        if (!$this->conversionContext) {
            $this->conversionContext = new ConversionContext([
                'calculate_fee' => true,
            ]);
        }
        
        // 执行转换
        $this->conversionResult = $this->unitConversion->convert(
            $this->fromUnit,
            $this->toUnit,
            (float)$this->operation['amount'],
            $this->conversionContext
        );
        
        $this->conversionExecuted = true;
    }
    
    /**
     * 构建操作数组
     */
    protected function buildOperations(): array
    {
        // 获取基础操作
        $operations = parent::build();
        
        if (empty($operations)) {
            return $operations;
        }
        
        // 如果没有转换结果，直接返回原始操作
        if (!$this->conversionResult) {
            return $operations;
        }
        
        // 获取转换后的金额和手续费
        $convertedAmount = $this->conversionResult->getToAmount();
        $fee = $this->conversionResult->getFee();
        
        // 更新操作金额
        if ($this->feeDeductFrom === 'from') {
            // 手续费从转出方扣除
            $operations[0]['amount'] = '-' . ($this->conversionResult->getFromAmount() + $fee);
            $operations[1]['amount'] = (string)$convertedAmount;
        } else {
            // 手续费从转入方扣除
            $operations[0]['amount'] = '-' . $this->conversionResult->getFromAmount();
            $operations[1]['amount'] = (string)($convertedAmount - $fee);
        }
        
        // 添加转换信息到 payload
        $conversionInfo = $this->getConversionContext();
        
        foreach ($operations as &$operation) {
            if (!isset($operation['payload'])) {
                $operation['payload'] = [];
            }
            $operation['payload'][ConversionAwareAccountOperations::CONVERSION_METADATA_KEY] = $conversionInfo;
        }
        
        // 如果有手续费，添加手续费扣除记录
        if ($fee > 0) {
            $feeOperation = $this->createFeeOperation($fee);
            if ($feeOperation) {
                $operations[] = $feeOperation;
            }
        }
        
        return $operations;
    }
    
    /**
     * 创建手续费扣除操作
     */
    protected function createFeeOperation(float $fee): ?array
    {
        $feeAccountId = $this->feeDeductFrom === 'from' 
            ? $this->operation['from']['account_id'] 
            : $this->operation['to']['account_id'];
            
        $feeBalanceType = $this->feeDeductFrom === 'from'
            ? $this->operation['from']['balance_type']
            : $this->operation['to']['balance_type'];
            
        $feeUnit = $this->feeDeductFrom === 'from' ? $this->fromUnit : $this->toUnit;
        
        return [
            'account_id' => $feeAccountId,
            'amount' => '-' . $fee,
            'balance_type' => $feeBalanceType,
            'event_id' => hash_code('unit_conversion_fee'),
            'payload' => [
                ConversionAwareAccountOperations::CONVERSION_METADATA_KEY => [
                    'type' => 'conversion_fee',
                    'fee_amount' => $fee,
                    'fee_unit' => $feeUnit,
                    'related_conversion' => $this->getConversionContext(),
                ],
            ],
        ];
    }
}