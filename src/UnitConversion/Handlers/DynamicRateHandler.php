<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Handlers;

use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 动态比例转换处理器
 * 
 * 支持从外部数据源（API、数据库等）获取实时转换率
 */
class DynamicRateHandler extends UnitConversionHandler
{
    public function handlerName(): string
    {
        return '动态比例转换';
    }
    
    public function handlerDescription(): string
    {
        return '从外部数据源获取实时转换率，适用于汇率等需要实时更新的场景';
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
        // 获取实时转换率
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
                'rate_source' => $this->getConfig()->get('source', 'api'),
                'rate_timestamp' => now()->toIso8601String(),
            ]
        );
    }
    
    /**
     * 获取动态转换率
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
        
        $source = $this->getConfig()->get('source', 'api');
        
        return match ($source) {
            'api' => $this->getRateFromApi($fromUnit, $toUnit, $context),
            'database' => $this->getRateFromDatabase($fromUnit, $toUnit, $context),
            'callback' => $this->getRateFromCallback($fromUnit, $toUnit, $context),
            default => throw new \RuntimeException("Unknown rate source: {$source}"),
        };
    }
    
    /**
     * 从API获取转换率
     */
    protected function getRateFromApi(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        ConversionContext $context
    ): float {
        $cacheKey = "unit_rate:{$fromUnit->code}:{$toUnit->code}";
        $cacheDuration = $this->getConfig()->get('cache_duration', 300); // 默认缓存5分钟
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($fromUnit, $toUnit) {
            $apiUrl = $this->getConfig()->get('api_url');
            $apiKey = $this->getConfig()->get('api_key');
            
            if (!$apiUrl) {
                throw new \RuntimeException('API URL not configured');
            }
            
            // 构建请求参数
            $params = [
                'from' => $fromUnit->code,
                'to' => $toUnit->code,
            ];
            
            if ($apiKey) {
                $params['api_key'] = $apiKey;
            }
            
            // 发送API请求
            $response = Http::get($apiUrl, $params);
            
            if (!$response->successful()) {
                throw new \RuntimeException('Failed to fetch rate from API');
            }
            
            $data = $response->json();
            $ratePath = $this->getConfig()->get('rate_path', 'rate');
            
            $rate = data_get($data, $ratePath);
            
            if ($rate === null) {
                throw new \RuntimeException('Rate not found in API response');
            }
            
            return (float) $rate;
        });
    }
    
    /**
     * 从数据库获取转换率
     */
    protected function getRateFromDatabase(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        ConversionContext $context
    ): float {
        $table = $this->getConfig()->get('rate_table', 'exchange_rates');
        $fromColumn = $this->getConfig()->get('from_column', 'from_code');
        $toColumn = $this->getConfig()->get('to_column', 'to_code');
        $rateColumn = $this->getConfig()->get('rate_column', 'rate');
        
        $rate = \DB::table($table)
            ->where($fromColumn, $fromUnit->code)
            ->where($toColumn, $toUnit->code)
            ->value($rateColumn);
            
        if ($rate === null) {
            // 尝试反向查询
            $reverseRate = \DB::table($table)
                ->where($fromColumn, $toUnit->code)
                ->where($toColumn, $fromUnit->code)
                ->value($rateColumn);
                
            if ($reverseRate !== null && $reverseRate != 0) {
                return 1 / $reverseRate;
            }
            
            throw new \RuntimeException("Rate not found in database for {$fromUnit->code} to {$toUnit->code}");
        }
        
        return (float) $rate;
    }
    
    /**
     * 从回调函数获取转换率
     */
    protected function getRateFromCallback(
        UnitDefinition $fromUnit,
        UnitDefinition $toUnit,
        ConversionContext $context
    ): float {
        $callback = $this->getConfig()->get('callback');
        
        if (!$callback || !is_callable($callback)) {
            throw new \RuntimeException('Rate callback not configured or not callable');
        }
        
        $rate = $callback($fromUnit, $toUnit, $context);
        
        if (!is_numeric($rate)) {
            throw new \RuntimeException('Rate callback must return a numeric value');
        }
        
        return (float) $rate;
    }
    
    /**
     * 计算动态手续费
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
        
        // 基础费用
        $baseFee = $fixedFee + $percentageFee;
        
        // 最小和最大费用限制
        if (isset($feeConfig['min'])) {
            $baseFee = max($baseFee, $feeConfig['min']);
        }
        
        if (isset($feeConfig['max'])) {
            $baseFee = min($baseFee, $feeConfig['max']);
        }
        
        // 动态费用调整
        $feeMultiplier = 1.0;
        
        // 根据用户等级调整
        if ($operator = $context->getOperator()) {
            $userLevel = $context->getParameter('user_level');
            if ($userLevel) {
                $levelMultipliers = $this->getConfig()->get('fee.level_multipliers', []);
                $feeMultiplier = $levelMultipliers[$userLevel] ?? 1.0;
            }
        }
        
        // 根据时间段调整（如非工作时间加收费用）
        $hourlyMultipliers = $this->getConfig()->get('fee.hourly_multipliers', []);
        $currentHour = now()->hour;
        if (isset($hourlyMultipliers[$currentHour])) {
            $feeMultiplier *= $hourlyMultipliers[$currentHour];
        }
        
        // 根据金额区间调整
        $amountTiers = $this->getConfig()->get('fee.amount_tiers', []);
        foreach ($amountTiers as $tier) {
            if ($amount >= ($tier['min'] ?? 0) && $amount <= ($tier['max'] ?? PHP_FLOAT_MAX)) {
                $feeMultiplier *= ($tier['multiplier'] ?? 1.0);
                break;
            }
        }
        
        $finalFee = $baseFee * $feeMultiplier;
        
        // 应用目标单位的精度
        return round($finalFee, $toUnit->precision);
    }
}