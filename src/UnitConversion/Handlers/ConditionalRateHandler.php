<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Handlers;

use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;

/**
 * 条件化转换处理器
 * 
 * 根据不同条件应用不同的转换率和费用策略
 */
class ConditionalRateHandler extends UnitConversionHandler
{
    public function handlerName(): string
    {
        return '条件化转换';
    }
    
    public function handlerDescription(): string
    {
        return '根据用户等级、活动期间等条件应用不同的转换率和费用';
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
        // 评估条件并获取适用的规则
        $rule = $this->evaluateConditions($context);
        
        // 获取基础转换率
        $baseRate = $this->getBaseRate($fromUnit, $toUnit);
        
        // 应用条件修正
        $rate = $this->applyRateModifiers($baseRate, $rule, $context);
        
        // 计算转换后的金额
        $convertedAmount = $amount * $rate;
        
        // 应用目标单位的精度
        $convertedAmount = round($convertedAmount, $toUnit->precision);
        
        // 计算手续费
        $fee = $this->calculateConditionalFee($convertedAmount, $fromUnit, $toUnit, $rule, $context);
        
        return new ConversionResult(
            fromUnit: $fromUnit,
            toUnit: $toUnit,
            fromAmount: $amount,
            toAmount: $convertedAmount,
            rate: $rate,
            fee: $fee,
            metadata: [
                'handler' => static::class,
                'rule_applied' => $rule['name'] ?? 'default',
                'conditions_met' => $rule['conditions'] ?? [],
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
        $rule = $this->evaluateConditions($context);
        $baseRate = $this->getBaseRate($fromUnit, $toUnit);
        
        return $this->applyRateModifiers($baseRate, $rule, $context);
    }
    
    /**
     * 获取基础转换率
     */
    protected function getBaseRate(UnitDefinition $fromUnit, UnitDefinition $toUnit): float
    {
        // 相同单位
        if ($fromUnit->id === $toUnit->id) {
            return 1.0;
        }
        
        // 使用单位定义中的基准值
        if ($fromUnit->category_id === $toUnit->category_id) {
            if ($fromUnit->base_value == 0) {
                throw new \RuntimeException("Source unit {$fromUnit->code} has invalid base value");
            }
            return $toUnit->base_value / $fromUnit->base_value;
        }
        
        // 跨类别转换需要在配置中定义
        $crossCategoryRate = $this->getConfig()->get("cross_category.{$fromUnit->code}.{$toUnit->code}");
        if ($crossCategoryRate === null) {
            throw new \RuntimeException("Cross-category conversion not configured for {$fromUnit->code} to {$toUnit->code}");
        }
        
        return (float) $crossCategoryRate;
    }
    
    /**
     * 评估条件并返回适用的规则
     */
    protected function evaluateConditions(ConversionContext $context): array
    {
        $rules = $this->getConfig()->get('rules', []);
        
        foreach ($rules as $rule) {
            if ($this->checkConditions($rule['conditions'] ?? [], $context)) {
                return $rule;
            }
        }
        
        // 返回默认规则
        return $this->getConfig()->get('default_rule', []);
    }
    
    /**
     * 检查条件是否满足
     */
    protected function checkConditions(array $conditions, ConversionContext $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->checkSingleCondition($condition, $context)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 检查单个条件
     */
    protected function checkSingleCondition(array $condition, ConversionContext $context): bool
    {
        $type = $condition['type'] ?? 'parameter';
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;
        
        $actualValue = match ($type) {
            'parameter' => $context->getParameter($field),
            'condition' => $context->getCondition($field),
            'operator' => $context->getOperator()?->getSessionHolderContext()[$field] ?? null,
            'time' => $this->getTimeValue($field),
            'date' => $this->getDateValue($field),
            default => null,
        };
        
        return $this->compareValues($actualValue, $operator, $value);
    }
    
    /**
     * 比较值
     */
    protected function compareValues(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'in' => in_array($actual, (array) $expected),
            'not_in' => !in_array($actual, (array) $expected),
            'between' => $actual >= $expected[0] && $actual <= $expected[1],
            'contains' => str_contains((string) $actual, (string) $expected),
            default => false,
        };
    }
    
    /**
     * 获取时间值
     */
    protected function getTimeValue(string $field): mixed
    {
        return match ($field) {
            'hour' => now()->hour,
            'day_of_week' => now()->dayOfWeek,
            'day_of_month' => now()->day,
            'month' => now()->month,
            'year' => now()->year,
            default => null,
        };
    }
    
    /**
     * 获取日期值
     */
    protected function getDateValue(string $field): mixed
    {
        return match ($field) {
            'today' => now()->toDateString(),
            'is_weekend' => now()->isWeekend(),
            'is_weekday' => now()->isWeekday(),
            default => null,
        };
    }
    
    /**
     * 应用比率修正
     */
    protected function applyRateModifiers(float $baseRate, array $rule, ConversionContext $context): float
    {
        $rate = $baseRate;
        
        // 固定修正值
        if (isset($rule['rate_adjustment'])) {
            $rate += $rule['rate_adjustment'];
        }
        
        // 百分比修正
        if (isset($rule['rate_multiplier'])) {
            $rate *= $rule['rate_multiplier'];
        }
        
        // 动态修正
        if (isset($rule['rate_callback']) && is_callable($rule['rate_callback'])) {
            $rate = $rule['rate_callback']($rate, $context);
        }
        
        return max($rate, 0); // 确保转换率不为负
    }
    
    /**
     * 计算条件化手续费
     */
    protected function calculateConditionalFee(
        float $amount,
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        array $rule,
        ConversionContext $context
    ): float {
        if (!$context->shouldCalculateFee()) {
            return 0;
        }
        
        $fee = 0;
        
        // 固定费用
        if (isset($rule['fee_fixed'])) {
            $fee += $rule['fee_fixed'];
        }
        
        // 百分比费用
        if (isset($rule['fee_percentage'])) {
            $fee += $amount * ($rule['fee_percentage'] / 100);
        }
        
        // 阶梯费用
        if (isset($rule['fee_tiers'])) {
            foreach ($rule['fee_tiers'] as $tier) {
                if ($amount >= ($tier['min'] ?? 0) && $amount <= ($tier['max'] ?? PHP_FLOAT_MAX)) {
                    $tierFee = $tier['fixed'] ?? 0;
                    $tierFee += $amount * (($tier['percentage'] ?? 0) / 100);
                    $fee += $tierFee;
                    break;
                }
            }
        }
        
        // 费用减免
        if (isset($rule['fee_discount'])) {
            $discount = $rule['fee_discount'];
            if (is_numeric($discount)) {
                // 固定减免
                $fee = max(0, $fee - $discount);
            } elseif (is_array($discount)) {
                // 百分比减免
                if (isset($discount['percentage'])) {
                    $fee *= (1 - $discount['percentage'] / 100);
                }
            }
        }
        
        // 最小和最大费用限制
        if (isset($rule['fee_min'])) {
            $fee = max($fee, $rule['fee_min']);
        }
        
        if (isset($rule['fee_max'])) {
            $fee = min($fee, $rule['fee_max']);
        }
        
        // 应用目标单位的精度
        return round($fee, $toUnit->precision);
    }
}