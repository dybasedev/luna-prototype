<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\TransactionContext;

/**
 * 标准支付提供者实现
 * 
 * 提供支付方式的标准管理功能
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StandardPaymentProvider implements PaymentProvider
{
    /**
     * 支付方式集合
     * 
     * @var array<string, PaymentMethod>
     */
    protected array $methods = [];
    
    /**
     * 支付方式状态
     * 
     * @var array<string, bool>
     */
    protected array $enabled = [];
    
    /**
     * 支付方式优先级
     * 
     * @var array<string, int>
     */
    protected array $priorities = [];
    
    /**
     * 默认支付方式
     * 
     * @var string|null
     */
    protected ?string $defaultMethod = null;
    
    /**
     * 配置
     * 
     * @var Repository
     */
    protected Repository $configuration;
    
    /**
     * 构造函数
     * 
     * @param array|Repository $configuration
     */
    public function __construct(array|Repository $configuration = [])
    {
        $this->configuration = $configuration instanceof Repository ? $configuration : new Repository($configuration);
    }
    
    /**
     * {@inheritdoc}
     */
    public function register(PaymentMethod $paymentMethod): void
    {
        $name = $paymentMethod->getName();
        $this->methods[$name] = $paymentMethod;
        
        // 默认启用
        if (!isset($this->enabled[$name])) {
            $this->enabled[$name] = true;
        }
        
        // 默认优先级
        if (!isset($this->priorities[$name])) {
            $this->priorities[$name] = 0;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function registerMany(array $paymentMethods): void
    {
        foreach ($paymentMethods as $method) {
            $this->register($method);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function unregister(string $name): void
    {
        unset($this->methods[$name]);
        unset($this->enabled[$name]);
        unset($this->priorities[$name]);
        
        if ($this->defaultMethod === $name) {
            $this->defaultMethod = null;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function get(string $name): ?PaymentMethod
    {
        return $this->methods[$name] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return isset($this->methods[$name]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->methods;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAvailable(SessionHolder $owner, ?TransactionContext $context = null): array
    {
        $available = [];
        
        foreach ($this->methods as $name => $method) {
            if ($this->isEnabled($name) && $method->isAvailable($owner, $context)) {
                $available[$name] = $method;
            }
        }
        
        return $this->sortByPriority($available);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getList(
        SessionHolder $owner,
        ?TransactionContext $context = null,
        bool $onlyAvailable = true
    ): array {
        $list = [];
        $methods = $onlyAvailable ? $this->getAvailable($owner, $context) : $this->getSorted();
        
        foreach ($methods as $name => $method) {
            $availability = $method->getAvailability($owner, $context);
            $capabilities = $method->getCapabilities();
            
            $list[$name] = [
                'name' => $name,
                'display_name' => $method->getDisplayName(),
                'description' => $method->getDescription(),
                'icon' => $method->getIcon(),
                'available' => $availability['available'] ?? false,
                'unavailable_reason' => $availability['reason'] ?? null,
                'priority' => $this->priorities[$name] ?? 0,
                'is_default' => $name === $this->defaultMethod,
                'capabilities' => $capabilities,
                'metadata' => $availability['metadata'] ?? [],
            ];
        }
        
        return $list;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setDefault(string $name): void
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Payment method '{$name}' not found");
        }
        
        $this->defaultMethod = $name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDefault(): ?PaymentMethod
    {
        if ($this->defaultMethod === null) {
            return null;
        }
        
        return $this->get($this->defaultMethod);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDefaultName(): ?string
    {
        return $this->defaultMethod;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setPriority(string $name, int $priority): void
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Payment method '{$name}' not found");
        }
        
        $this->priorities[$name] = $priority;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSorted(): array
    {
        return $this->sortByPriority($this->methods);
    }
    
    /**
     * {@inheritdoc}
     */
    public function enable(string $name): void
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Payment method '{$name}' not found");
        }
        
        $this->enabled[$name] = true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function disable(string $name): void
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Payment method '{$name}' not found");
        }
        
        $this->enabled[$name] = false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEnabled(string $name): bool
    {
        return $this->enabled[$name] ?? false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): array
    {
        return $this->configuration->all();
    }
    
    /**
     * {@inheritdoc}
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = new Repository($configuration);
    }
    
    /**
     * 按优先级排序
     * 
     * @param array<string, PaymentMethod> $methods
     * @return array<string, PaymentMethod>
     */
    protected function sortByPriority(array $methods): array
    {
        $sorted = $methods;
        
        uksort($sorted, function ($a, $b) {
            $priorityA = $this->priorities[$a] ?? 0;
            $priorityB = $this->priorities[$b] ?? 0;
            
            // 优先级高的排前面
            return $priorityB <=> $priorityA;
        });
        
        return $sorted;
    }
}