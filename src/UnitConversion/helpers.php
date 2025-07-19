<?php

use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;

if (!function_exists('luna_unit_conversion')) {
    /**
     * 获取单位转换模块实例
     * 
     * @return LunaUnitConversion|null
     */
    function luna_unit_conversion(): ?LunaUnitConversion
    {
        try {
            return app(LunaUnitConversion::class);
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('luna_convert_unit')) {
    /**
     * 快速单位转换
     * 
     * @param string $from 源单位代码
     * @param string $to 目标单位代码
     * @param float $amount 转换数量
     * @param array $context 上下文参数
     * @return float|null 转换后的数量，失败返回 null
     */
    function luna_convert_unit(string $from, string $to, float $amount, array $context = []): ?float
    {
        $unitConversion = luna_unit_conversion();
        if (!$unitConversion) {
            return null;
        }
        
        try {
            $conversionContext = \Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext::make($context);
            $result = $unitConversion->convert($from, $to, $amount, $conversionContext);
            return $result->getNetAmount();
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('luna_format_unit_value')) {
    /**
     * 格式化单位值
     * 
     * @param float $value 数值
     * @param string $unitCode 单位代码
     * @return string 格式化后的字符串
     */
    function luna_format_unit_value(float $value, string $unitCode): string
    {
        $unitConversion = luna_unit_conversion();
        if (!$unitConversion) {
            return number_format($value, 2) . ' ' . $unitCode;
        }
        
        $unit = $unitConversion->getUnit($unitCode);
        if (!$unit) {
            return number_format($value, 2) . ' ' . $unitCode;
        }
        
        return $unit->formatValue($value);
    }
}