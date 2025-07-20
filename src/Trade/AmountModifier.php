<?php

namespace Dybasedev\LunaPrototype\Trade;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * 金额修饰器抽象类
 * 
 * 提供灵活的金额修改机制，支持各种业务场景如折扣、优惠券、运费、税费等。
 * 所有修饰器必须是可序列化的，以便在交易预览和交易记录中持久化。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
abstract class AmountModifier implements Arrayable, Jsonable, \JsonSerializable
{
    /**
     * @var string 修饰器唯一标识
     */
    protected string $id;
    
    /**
     * @var string 修饰器名称
     */
    protected string $name;
    
    /**
     * @var string 修饰器类型（discount|fee|tax|promotion|coupon|custom）
     */
    protected string $type;
    
    /**
     * @var int 优先级（数字越小优先级越高）
     */
    protected int $priority = 100;
    
    /**
     * @var array 元数据
     */
    protected array $metadata = [];
    
    /**
     * @var \DateTimeInterface 应用时间
     */
    protected \DateTimeInterface $appliedAt;
    
    /**
     * 构造函数
     * 
     * @param string $id 唯一标识
     * @param string $name 名称
     * @param string $type 类型
     */
    public function __construct(string $id, string $name, string $type)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->appliedAt = now();
    }
    
    /**
     * 计算修改后的金额
     * 
     * @param float $amount 当前金额
     * @param TransactionPreview $preview 交易预览对象
     * @return float 修改后的金额
     */
    abstract public function calculate(float $amount, TransactionPreview $preview): float;
    
    /**
     * 验证修饰器是否可以应用
     * 
     * @param TransactionPreview $preview 交易预览对象
     * @return bool
     */
    abstract public function canApply(TransactionPreview $preview): bool;
    
    /**
     * 获取修饰器的描述信息
     * 
     * @return string
     */
    abstract public function getDescription(): string;
    
    /**
     * 获取修饰器产生的金额变化
     * 
     * @param float $originalAmount 原始金额
     * @param TransactionPreview $preview 交易预览对象
     * @return float 金额变化（正数表示增加，负数表示减少）
     */
    public function getAmountChange(float $originalAmount, TransactionPreview $preview): float
    {
        if (!$this->canApply($preview)) {
            return 0.0;
        }
        
        $newAmount = $this->calculate($originalAmount, $preview);
        return $newAmount - $originalAmount;
    }
    
    /**
     * 设置优先级
     * 
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
    
    /**
     * 设置元数据
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * 批量设置元数据
     * 
     * @param array $metadata
     * @return self
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }
    
    /**
     * 获取ID
     * 
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * 获取名称
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * 获取类型
     * 
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * 获取优先级
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }
    
    /**
     * 获取元数据
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }
        
        return $this->metadata[$key] ?? $default;
    }
    
    /**
     * 获取应用时间
     * 
     * @return \DateTimeInterface
     */
    public function getAppliedAt(): \DateTimeInterface
    {
        return $this->appliedAt;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'priority' => $this->priority,
            'description' => $this->getDescription(),
            'metadata' => $this->metadata,
            'applied_at' => $this->appliedAt->toDateTimeString(),
        ];
    }
    
    /**
     * 转换为JSON
     * 
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    
    /**
     * JSON序列化
     * 
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    /**
     * 从数组创建修饰器实例
     * 
     * @param array $data
     * @return static
     */
    abstract public static function fromArray(array $data): static;
}