<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Events;

use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;

/**
 * 单位转换完成事件
 * 
 * 用于外部系统监听单位转换事件，实现日志记录或其他业务逻辑
 */
class ConversionCompleted
{
    public function __construct(
        public readonly UnitDefinition $fromUnit,
        public readonly UnitDefinition $toUnit,
        public readonly float $fromAmount,
        public readonly ConversionResult $result,
        public readonly ConversionContext $context
    ) {
    }
    
    /**
     * 获取转换摘要信息
     */
    public function getSummary(): array
    {
        return [
            'from' => [
                'unit_id' => $this->fromUnit->id,
                'unit_code' => $this->fromUnit->code,
                'amount' => $this->fromAmount,
            ],
            'to' => [
                'unit_id' => $this->toUnit->id,
                'unit_code' => $this->toUnit->code,
                'amount' => $this->result->getToAmount(),
            ],
            'rate' => $this->result->getRate(),
            'fee' => $this->result->getFee(),
            'net_amount' => $this->result->getNetAmount(),
            'metadata' => $this->result->getMetadata(),
            'context' => $this->context->toArray(),
            'converted_at' => now()->toIso8601String(),
        ];
    }
}