<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Handlers;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\ModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelInstance;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;

/**
 * 单位转换处理器基类
 * 
 * 所有单位转换处理器都需要继承此类
 */
abstract class UnitConversionHandler extends BaseHandler implements ModelHandler
{
    use WithModelInstance;
    
    /**
     * 执行单位转换
     * 
     * @param UnitDefinition $fromUnit 源单位
     * @param UnitDefinition $toUnit 目标单位
     * @param float $amount 转换数量
     * @param ConversionContext $context 转换上下文
     * @return ConversionResult 转换结果
     */
    abstract public function convert(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        float $amount,
        ConversionContext $context
    ): ConversionResult;
    
    /**
     * 获取转换率
     * 
     * @param UnitDefinition $fromUnit 源单位
     * @param UnitDefinition $toUnit 目标单位
     * @param ConversionContext $context 转换上下文
     * @return float 转换率
     */
    abstract public function getRate(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        ConversionContext $context
    ): float;
    
    /**
     * 计算手续费
     * 
     * @param float $amount 转换金额
     * @param UnitDefinition $fromUnit 源单位
     * @param UnitDefinition $toUnit 目标单位
     * @param ConversionContext $context 转换上下文
     * @return float 手续费
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
        
        // 默认无手续费，子类可以覆盖此方法
        return 0;
    }
    
    /**
     * 验证转换是否可行
     * 
     * @param UnitDefinition $fromUnit 源单位
     * @param UnitDefinition $toUnit 目标单位
     * @param float $amount 转换数量
     * @param ConversionContext $context 转换上下文
     * @return bool 是否可以转换
     */
    public function canConvert(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        float $amount,
        ConversionContext $context
    ): bool {
        // 默认总是可以转换，子类可以覆盖此方法添加额外验证
        return $amount > 0;
    }
    
    /**
     * 获取支持的单位类别
     * 
     * @return array<string> 支持的类别名称列表，空数组表示支持所有类别
     */
    public function getSupportedCategories(): array
    {
        return [];
    }
    
    /**
     * 判断是否支持特定的单位转换
     * 
     * @param UnitDefinition $fromUnit 源单位
     * @param UnitDefinition $toUnit 目标单位
     * @return bool
     */
    public function supports(UnitDefinition $fromUnit, UnitDefinition $toUnit): bool
    {
        $supportedCategories = $this->getSupportedCategories();
        
        // 如果没有限制类别，则支持所有
        if (empty($supportedCategories)) {
            return true;
        }
        
        // 检查源单位和目标单位的类别是否在支持列表中
        $fromCategory = $fromUnit->category->name;
        $toCategory = $toUnit->category->name;
        
        return in_array($fromCategory, $supportedCategories) 
            && in_array($toCategory, $supportedCategories);
    }
    
    /**
     * 获取处理器优先级
     * 
     * @return int 优先级，值越大优先级越高
     */
    public function getPriority(): int
    {
        return 0;
    }
}