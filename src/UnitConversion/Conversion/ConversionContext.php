<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Conversion;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Contracts\Support\Arrayable;

/**
 * 单位转换上下文
 * 
 * 用于传递转换过程中的额外信息，如用户、业务场景、特殊条件等
 */
class ConversionContext implements Arrayable
{
    /**
     * @var SessionHolder|null 执行转换的用户
     */
    protected ?SessionHolder $operator = null;
    
    /**
     * @var array 额外参数
     */
    protected array $parameters = [];
    
    /**
     * @var array 特殊条件
     */
    protected array $conditions = [];
    
    /**
     * @var bool 是否记录日志
     */
    protected bool $shouldLog = true;
    
    /**
     * @var bool 是否计算手续费
     */
    protected bool $calculateFee = true;
    
    /**
     * 构造函数
     */
    public function __construct(array $data = [])
    {
        if (isset($data['operator'])) {
            $this->operator = $data['operator'];
        }
        
        if (isset($data['parameters'])) {
            $this->parameters = $data['parameters'];
        }
        
        if (isset($data['conditions'])) {
            $this->conditions = $data['conditions'];
        }
        
        if (isset($data['should_log'])) {
            $this->shouldLog = $data['should_log'];
        }
        
        if (isset($data['calculate_fee'])) {
            $this->calculateFee = $data['calculate_fee'];
        }
    }
    
    /**
     * 设置操作者
     */
    public function setOperator(?SessionHolder $operator): static
    {
        $this->operator = $operator;
        return $this;
    }
    
    /**
     * 获取操作者
     */
    public function getOperator(): ?SessionHolder
    {
        return $this->operator;
    }
    
    /**
     * 设置参数
     */
    public function setParameter(string $key, mixed $value): static
    {
        $this->parameters[$key] = $value;
        return $this;
    }
    
    /**
     * 获取参数
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }
    
    /**
     * 获取所有参数
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
    
    /**
     * 设置条件
     */
    public function setCondition(string $key, mixed $value): static
    {
        $this->conditions[$key] = $value;
        return $this;
    }
    
    /**
     * 检查条件
     */
    public function hasCondition(string $key): bool
    {
        return isset($this->conditions[$key]);
    }
    
    /**
     * 获取条件
     */
    public function getCondition(string $key, mixed $default = null): mixed
    {
        return $this->conditions[$key] ?? $default;
    }
    
    /**
     * 获取所有条件
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }
    
    /**
     * 设置是否记录日志
     */
    public function setShouldLog(bool $shouldLog): static
    {
        $this->shouldLog = $shouldLog;
        return $this;
    }
    
    /**
     * 是否记录日志
     */
    public function shouldLog(): bool
    {
        return $this->shouldLog;
    }
    
    /**
     * 设置是否计算手续费
     */
    public function setCalculateFee(bool $calculateFee): static
    {
        $this->calculateFee = $calculateFee;
        return $this;
    }
    
    /**
     * 是否计算手续费
     */
    public function shouldCalculateFee(): bool
    {
        return $this->calculateFee;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'operator_id' => $this->operator?->getOperatorId(),
            'operator_type' => $this->operator?->getOperatorType(),
            'parameters' => $this->parameters,
            'conditions' => $this->conditions,
            'should_log' => $this->shouldLog,
            'calculate_fee' => $this->calculateFee,
        ];
    }
    
    /**
     * 创建一个新的上下文实例
     */
    public static function make(array $data = []): static
    {
        return new static($data);
    }
}