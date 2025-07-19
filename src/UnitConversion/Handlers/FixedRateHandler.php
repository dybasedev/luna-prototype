<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Handlers;

use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;

/**
 * 固定比例转换处理器
 * 
 * 使用固定的转换率进行单位转换，适用于同类别单位之间的标准转换
 */
class FixedRateHandler extends UnitConversionHandler
{
    public function handlerName(): string
    {
        return '固定比例转换';
    }
    
    public function handlerDescription(): string
    {
        return '使用固定比例进行单位转换，适用于标准单位之间的转换';
    }
    
    /**
     * 执行单位转换
     */
    public function convert(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        float $amount,
        ConversionContext $context
    ): ConversionResult {
        // 获取转换率
        $rate = $this->getRate($fromUnit, $toUnit, $context);
        
        // 计算转换后的金额
        $convertedAmount = $amount * $rate;
        
        // 应用目标单位的精度
        $convertedAmount = round($convertedAmount, $toUnit->precision);
        
        // 计算手续费
        $fee = $this->calculateFee($convertedAmount, $fromUnit, $toUnit, $context);
        
        return new ConversionResult(
            fromUnit: $fromUnit,
            toUnit: $toUnit,
            fromAmount: $amount,
            toAmount: $convertedAmount,
            rate: $rate,
            fee: $fee,
            metadata: [
                'handler' => static::class,
                'base_rate_used' => true,
            ]
        );
    }
    
    /**
     * 获取转换率
     */
    public function getRate(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        ConversionContext $context
    ): float {
        // 相同单位
        if ($fromUnit->id === $toUnit->id) {
            return 1.0;
        }
        
        // 检查配置中是否有自定义比例
        $customRate = $this->getConfig()->get('rates.' . $fromUnit->code . '.' . $toUnit->code);
        if ($customRate !== null) {
            return (float) $customRate;
        }
        
        // 使用基准值计算
        if ($fromUnit->category_id === $toUnit->category_id) {
            if ($fromUnit->base_value == 0) {
                throw new \RuntimeException("Source unit {$fromUnit->code} has invalid base value");
            }
            return $toUnit->base_value / $fromUnit->base_value;
        }
        
        throw new \RuntimeException("Cannot convert between different categories using fixed rate handler");
    }
    
    /**
     * 计算手续费
     */
    public function calculateFee(
        float $amount,
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        ConversionContext $context
    ): float {
        if (!$context->shouldCalculateFee()) {
            return 0;
        }
        
        // 从配置获取费率
        $feeConfig = $this->getConfig()->get('fee', []);
        
        // 固定费用
        $fixedFee = $feeConfig['fixed'] ?? 0;
        
        // 百分比费用
        $percentageFee = 0;
        if (isset($feeConfig['percentage'])) {
            $percentageFee = $amount * ($feeConfig['percentage'] / 100);
        }
        
        // 总费用
        $totalFee = $fixedFee + $percentageFee;
        
        // 最小和最大费用限制
        if (isset($feeConfig['min'])) {
            $totalFee = max($totalFee, $feeConfig['min']);
        }
        
        if (isset($feeConfig['max'])) {
            $totalFee = min($totalFee, $feeConfig['max']);
        }
        
        // 应用目标单位的精度
        return round($totalFee, $toUnit->precision);
    }
    
    /**
     * 支持同类别的所有单位转换
     */
    public function supports(UnitDefinition $fromUnit, UnitDefinition $toUnit): bool
    {
        // 固定比例处理器只支持同类别单位之间的转换
        return $fromUnit->category_id === $toUnit->category_id;
    }
}