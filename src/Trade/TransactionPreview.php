<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Support\Collection;

/**
 * 交易预览
 * 
 * 在正式创建交易之前，生成交易预览供用户确认和系统处理。
 * 使用灵活的修饰器模式处理金额变化，支持各种业务场景的扩展。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TransactionPreview
{
    /**
     * @var SessionHolder 交易所有者
     */
    protected SessionHolder $owner;
    
    /**
     * @var Collection<TradableItem> 可交易项目集合
     */
    protected Collection $items;
    
    /**
     * @var float 原始总金额（商品原价总和）
     */
    protected float $originAmount = 0.0;
    
    /**
     * @var float 基础金额（商品实际价格总和，未应用修饰器）
     */
    protected float $baseAmount = 0.0;
    
    /**
     * @var float 最终金额（应用所有修饰器后）
     */
    protected float $finalAmount = 0.0;
    
    /**
     * @var Collection<AmountModifier> 金额修饰器集合
     */
    protected Collection $modifiers;
    
    /**
     * @var array 可用的支付方式
     */
    protected array $availablePaymentMethods = [];
    
    /**
     * @var array 额外的元数据
     */
    protected array $metadata = [];
    
    /**
     * @var TransactionContext|null 交易上下文
     */
    protected ?TransactionContext $context = null;
    
    /**
     * @var string|null 渠道标识
     */
    protected ?string $channel = null;
    
    /**
     * @var \DateTimeInterface 预览创建时间
     */
    protected \DateTimeInterface $createdAt;
    
    /**
     * @var \DateTimeInterface|null 预览过期时间
     */
    protected ?\DateTimeInterface $expiresAt = null;
    
    public function __construct(SessionHolder $owner)
    {
        $this->owner = $owner;
        $this->items = collect();
        $this->modifiers = collect();
        $this->createdAt = now();
    }
    
    /**
     * 添加可交易项目
     * 
     * @param Tradable $tradable
     * @param float $quantity
     * @param array $options
     * @return self
     */
    public function addItem(Tradable $tradable, float $quantity = 1.0, array $options = []): self
    {
        $item = new TradableItem($tradable, $quantity, $options);
        $this->items->push($item);
        
        // 重新计算金额
        $this->recalculate();
        
        return $this;
    }
    
    /**
     * 添加多个可交易项目
     * 
     * @param array<Tradable> $tradables
     * @param array<int|string, float> $quantities
     * @return self
     */
    public function addItems(array $tradables, array $quantities = []): self
    {
        foreach ($tradables as $tradable) {
            $quantity = $quantities[$tradable->getTradableId()] ?? 1.0;
            $this->addItem($tradable, $quantity);
        }
        
        return $this;
    }
    
    /**
     * 移除可交易项目
     * 
     * @param int|string $tradableId
     * @return self
     */
    public function removeItem(int|string $tradableId): self
    {
        $this->items = $this->items->reject(function (TradableItem $item) use ($tradableId) {
            return $item->getTradable()->getTradableId() === $tradableId;
        });
        
        $this->recalculate();
        
        return $this;
    }
    
    /**
     * 添加金额修饰器
     * 
     * @param AmountModifier $modifier
     * @return self
     * @throws \InvalidArgumentException
     */
    public function addModifier(AmountModifier $modifier): self
    {
        if (!$modifier->canApply($this)) {
            throw new \InvalidArgumentException(
                "Modifier '{$modifier->getName()}' cannot be applied to this preview"
            );
        }
        
        // 移除同ID的旧修饰器
        $this->modifiers = $this->modifiers->reject(
            fn(AmountModifier $m) => $m->getId() === $modifier->getId()
        );
        
        // 添加新修饰器
        $this->modifiers->push($modifier);
        
        // 按优先级排序
        $this->modifiers = $this->modifiers->sortBy(fn(AmountModifier $m) => $m->getPriority());
        
        $this->recalculate();
        
        return $this;
    }
    
    /**
     * 批量添加金额修饰器
     * 
     * @param array<AmountModifier> $modifiers
     * @return self
     */
    public function addModifiers(array $modifiers): self
    {
        foreach ($modifiers as $modifier) {
            $this->addModifier($modifier);
        }
        
        return $this;
    }
    
    /**
     * 移除金额修饰器
     * 
     * @param string $modifierId
     * @return self
     */
    public function removeModifier(string $modifierId): self
    {
        $this->modifiers = $this->modifiers->reject(
            fn(AmountModifier $m) => $m->getId() === $modifierId
        );
        
        $this->recalculate();
        
        return $this;
    }
    
    /**
     * 移除指定类型的所有修饰器
     * 
     * @param string $type
     * @return self
     */
    public function removeModifiersByType(string $type): self
    {
        $this->modifiers = $this->modifiers->reject(
            fn(AmountModifier $m) => $m->getType() === $type
        );
        
        $this->recalculate();
        
        return $this;
    }
    
    /**
     * 清空所有修饰器
     * 
     * @return self
     */
    public function clearModifiers(): self
    {
        $this->modifiers = collect();
        $this->recalculate();
        
        return $this;
    }
    
    /**
     * 设置交易上下文
     * 
     * @param TransactionContext $context
     * @return self
     */
    public function withContext(TransactionContext $context): self
    {
        $this->context = $context;
        return $this;
    }
    
    /**
     * 设置渠道
     * 
     * @param string $channel
     * @return self
     */
    public function fromChannel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }
    
    /**
     * 获取指定类型的修饰器
     * 
     * @param string $type
     * @return Collection<AmountModifier>
     */
    public function getModifiersByType(string $type): Collection
    {
        return $this->modifiers->filter(fn(AmountModifier $m) => $m->getType() === $type);
    }
    
    /**
     * 获取指定ID的修饰器
     * 
     * @param string $id
     * @return AmountModifier|null
     */
    public function getModifier(string $id): ?AmountModifier
    {
        return $this->modifiers->firstWhere('id', $id);
    }
    
    /**
     * 检查是否有指定类型的修饰器
     * 
     * @param string $type
     * @return bool
     */
    public function hasModifierType(string $type): bool
    {
        return $this->modifiers->contains(fn(AmountModifier $m) => $m->getType() === $type);
    }
    
    /**
     * 设置可用的支付方式
     * 
     * @param array $methods
     * @return self
     */
    public function setAvailablePaymentMethods(array $methods): self
    {
        $this->availablePaymentMethods = $methods;
        return $this;
    }
    
    /**
     * 设置预览过期时间
     * 
     * @param \DateTimeInterface|int $expiresAt 过期时间或分钟数
     * @return self
     */
    public function expiresAt(\DateTimeInterface|int $expiresAt): self
    {
        if (is_int($expiresAt)) {
            $this->expiresAt = now()->addMinutes($expiresAt);
        } else {
            $this->expiresAt = $expiresAt;
        }
        
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
     * 重新计算金额
     * 
     * @return void
     */
    protected function recalculate(): void
    {
        // 计算原始总金额（商品原价）
        $this->originAmount = 0.0;
        $this->baseAmount = 0.0;
        
        foreach ($this->items as $item) {
            $this->originAmount += $item->getOriginAmount();
            $this->baseAmount += $item->getAmount();
        }
        
        // 从基础金额开始，依次应用所有修饰器
        $currentAmount = $this->baseAmount;
        
        foreach ($this->modifiers as $modifier) {
            if ($modifier->canApply($this)) {
                $currentAmount = $modifier->calculate($currentAmount, $this);
            }
        }
        
        // 确保最终金额不小于0
        $this->finalAmount = max(0, $currentAmount);
    }
    
    /**
     * 获取修饰器产生的总变化金额
     * 
     * @return float
     */
    public function getTotalModifierAmount(): float
    {
        return $this->finalAmount - $this->baseAmount;
    }
    
    /**
     * 获取每个修饰器的详细影响
     * 
     * @return array
     */
    public function getModifierBreakdown(): array
    {
        $breakdown = [];
        $currentAmount = $this->baseAmount;
        
        foreach ($this->modifiers as $modifier) {
            if ($modifier->canApply($this)) {
                $oldAmount = $currentAmount;
                $currentAmount = $modifier->calculate($currentAmount, $this);
                $change = $currentAmount - $oldAmount;
                
                $breakdown[] = [
                    'modifier' => $modifier->toArray(),
                    'amount_before' => $oldAmount,
                    'amount_after' => $currentAmount,
                    'amount_change' => $change,
                ];
            }
        }
        
        return $breakdown;
    }
    
    /**
     * 检查预览是否已过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        
        return $this->expiresAt->isPast();
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'owner' => [
                'id' => $this->owner->getOperatorId(),
                'type' => $this->owner->getOperatorType(),
            ],
            'items' => $this->items->map(fn(TradableItem $item) => $item->toArray())->values()->all(),
            'origin_amount' => $this->originAmount,
            'base_amount' => $this->baseAmount,
            'final_amount' => $this->finalAmount,
            'total_modifier_amount' => $this->getTotalModifierAmount(),
            'modifiers' => $this->modifiers->map(fn(AmountModifier $m) => $m->toArray())->values()->all(),
            'modifier_breakdown' => $this->getModifierBreakdown(),
            'available_payment_methods' => $this->availablePaymentMethods,
            'channel' => $this->channel,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toDateTimeString(),
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'is_expired' => $this->isExpired(),
        ];
    }
    
    // Getters
    
    public function getOwner(): SessionHolder
    {
        return $this->owner;
    }
    
    public function getItems(): Collection
    {
        return $this->items;
    }
    
    public function getOriginAmount(): float
    {
        return $this->originAmount;
    }
    
    public function getBaseAmount(): float
    {
        return $this->baseAmount;
    }
    
    public function getFinalAmount(): float
    {
        return $this->finalAmount;
    }
    
    /**
     * 获取最终金额
     * 
     * @return float
     */
    public function getAmount(): float
    {
        return $this->finalAmount;
    }
    
    public function getModifiers(): Collection
    {
        return $this->modifiers;
    }
    
    public function getContext(): ?TransactionContext
    {
        return $this->context;
    }
    
    public function getChannel(): ?string
    {
        return $this->channel;
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    public function getAvailablePaymentMethods(): array
    {
        return $this->availablePaymentMethods;
    }
    
    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }
}