<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Conversion;

use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Illuminate\Contracts\Support\Arrayable;

/**
 * 单位转换结果
 */
class ConversionResult implements Arrayable
{
    /**
     * 构造函数
     */
    public function __construct(
        protected UnitDefinition $fromUnit,
        protected UnitDefinition $toUnit,
        protected float $fromAmount,
        protected float $toAmount,
        protected float $rate,
        protected float $fee = 0,
        protected array $metadata = []
    ) {}
    
    /**
     * 获取源单位
     */
    public function getFromUnit(): UnitDefinition
    {
        return $this->fromUnit;
    }
    
    /**
     * 获取目标单位
     */
    public function getToUnit(): UnitDefinition
    {
        return $this->toUnit;
    }
    
    /**
     * 获取原始数量
     */
    public function getFromAmount(): float
    {
        return $this->fromAmount;
    }
    
    /**
     * 获取转换后数量
     */
    public function getToAmount(): float
    {
        return $this->toAmount;
    }
    
    /**
     * 获取转换率
     */
    public function getRate(): float
    {
        return $this->rate;
    }
    
    /**
     * 获取手续费
     */
    public function getFee(): float
    {
        return $this->fee;
    }
    
    /**
     * 获取净转换金额（扣除手续费）
     */
    public function getNetAmount(): float
    {
        return $this->toAmount - $this->fee;
    }
    
    /**
     * 获取元数据
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * 设置元数据
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    /**
     * 添加元数据
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * 格式化源数量
     */
    public function formatFromAmount(): string
    {
        return $this->fromUnit->formatValue($this->fromAmount);
    }
    
    /**
     * 格式化目标数量
     */
    public function formatToAmount(): string
    {
        return $this->toUnit->formatValue($this->toAmount);
    }
    
    /**
     * 格式化净数量
     */
    public function formatNetAmount(): string
    {
        return $this->toUnit->formatValue($this->getNetAmount());
    }
    
    /**
     * 格式化手续费
     */
    public function formatFee(): string
    {
        return $this->toUnit->formatValue($this->fee);
    }
    
    /**
     * 获取转换描述
     */
    public function getDescription(): string
    {
        $description = sprintf(
            '%s → %s',
            $this->formatFromAmount(),
            $this->formatToAmount()
        );
        
        if ($this->fee > 0) {
            $description .= sprintf(' (费用: %s)', $this->formatFee());
        }
        
        return $description;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'from_unit' => [
                'id' => $this->fromUnit->id,
                'code' => $this->fromUnit->code,
                'symbol' => $this->fromUnit->symbol,
                'display_name' => $this->fromUnit->display_name,
            ],
            'to_unit' => [
                'id' => $this->toUnit->id,
                'code' => $this->toUnit->code,
                'symbol' => $this->toUnit->symbol,
                'display_name' => $this->toUnit->display_name,
            ],
            'from_amount' => $this->fromAmount,
            'to_amount' => $this->toAmount,
            'rate' => $this->rate,
            'fee' => $this->fee,
            'net_amount' => $this->getNetAmount(),
            'formatted' => [
                'from_amount' => $this->formatFromAmount(),
                'to_amount' => $this->formatToAmount(),
                'fee' => $this->formatFee(),
                'net_amount' => $this->formatNetAmount(),
                'description' => $this->getDescription(),
            ],
            'metadata' => $this->metadata,
        ];
    }
}