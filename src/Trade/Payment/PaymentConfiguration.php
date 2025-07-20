<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * 支付配置类
 * 
 * 用于管理支付系统的配置，包括支付方式的注册、配置等
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class PaymentConfiguration
{
    /**
     * 支付提供者
     * 
     * @var PaymentProvider|null
     */
    protected ?PaymentProvider $provider = null;
    
    /**
     * 支付方式注册表
     * 
     * @var array<string, array|PaymentMethod>
     */
    protected array $methods = [];
    
    /**
     * 全局配置
     * 
     * @var Repository
     */
    protected Repository $globalConfig;
    
    /**
     * 默认支付方式
     * 
     * @var string|null
     */
    protected ?string $defaultMethod = null;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->globalConfig = new Repository([]);
    }
    
    /**
     * 创建新的配置实例
     * 
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * 设置支付提供者
     * 
     * @param PaymentProvider $provider
     * @return $this
     */
    public function withProvider(PaymentProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }
    
    /**
     * 使用标准支付提供者
     * 
     * @param array $config
     * @return $this
     */
    public function useStandardProvider(array $config = []): static
    {
        $provider = new StandardPaymentProvider();
        $provider->setConfiguration($config);
        $this->provider = $provider;
        return $this;
    }
    
    /**
     * 注册支付方式
     * 
     * @param string $name
     * @param PaymentMethod|string|array $method
     * @param array $config
     * @return $this
     */
    public function registerMethod(string $name, $method, array $config = []): static
    {
        if ($method instanceof PaymentMethod) {
            $this->methods[$name] = $method;
        } elseif (is_string($method)) {
            // 类名
            $this->methods[$name] = [
                'class' => $method,
                'config' => $config,
            ];
        } elseif (is_array($method)) {
            // 数组配置
            $this->methods[$name] = array_merge($method, $config);
        }
        
        return $this;
    }
    
    /**
     * 批量注册支付方式
     * 
     * @param array $methods
     * @return $this
     */
    public function registerMethods(array $methods): static
    {
        foreach ($methods as $name => $method) {
            if (is_array($method) && isset($method['class'])) {
                $this->registerMethod($name, $method['class'], $method['config'] ?? []);
            } else {
                $this->registerMethod($name, $method);
            }
        }
        
        return $this;
    }
    
    /**
     * 设置默认支付方式
     * 
     * @param string $name
     * @return $this
     */
    public function setDefaultMethod(string $name): static
    {
        $this->defaultMethod = $name;
        return $this;
    }
    
    /**
     * 设置全局配置
     * 
     * @param array $config
     * @return $this
     */
    public function setGlobalConfig(array $config): static
    {
        $this->globalConfig = new Repository($config);
        return $this;
    }
    
    /**
     * 合并全局配置
     * 
     * @param array $config
     * @return $this
     */
    public function mergeGlobalConfig(array $config): static
    {
        foreach ($config as $key => $value) {
            $this->globalConfig->set($key, $value);
        }
        return $this;
    }
    
    /**
     * 构建并返回支付提供者
     * 
     * @return PaymentProvider
     */
    public function build(): PaymentProvider
    {
        if (!$this->provider) {
            $this->useStandardProvider();
        }
        
        // 实例化并注册所有支付方式
        foreach ($this->methods as $name => $method) {
            $instance = $this->createMethodInstance($name, $method);
            if ($instance) {
                $this->provider->register($instance);
            }
        }
        
        // 设置默认支付方式
        if ($this->defaultMethod && $this->provider->has($this->defaultMethod)) {
            $this->provider->setDefault($this->defaultMethod);
        }
        
        // 设置全局配置
        $this->provider->setConfiguration($this->globalConfig->all());
        
        return $this->provider;
    }
    
    /**
     * 创建支付方式实例
     * 
     * @param string $name
     * @param mixed $method
     * @return PaymentMethod|null
     */
    protected function createMethodInstance(string $name, $method): ?PaymentMethod
    {
        if ($method instanceof PaymentMethod) {
            return $method;
        }
        
        if (is_array($method) && isset($method['class'])) {
            $className = $method['class'];
            
            // 合并全局配置和方法特定配置
            $config = new Repository($this->globalConfig->all());
            if (isset($method['config']) && is_array($method['config'])) {
                foreach ($method['config'] as $key => $value) {
                    $config->set($key, $value);
                }
            }
            
            if (!class_exists($className)) {
                throw new \InvalidArgumentException("Payment method class '{$className}' not found");
            }
            
            $instance = new $className($config);
            
            if (!$instance instanceof PaymentMethod) {
                throw new \InvalidArgumentException(
                    "Class '{$className}' must implement PaymentMethod interface"
                );
            }
            
            return $instance;
        }
        
        return null;
    }
    
    /**
     * 注册资产账户支付方式
     * 
     * @param array $config
     * @return $this
     */
    public function registerAssetsAccountMethod(array $config = []): static
    {
        $defaultConfig = [
            'name' => 'assets_account',
            'display_name' => '账户余额支付',
            'description' => '使用账户余额进行支付',
            'account_type' => 'balance',
            'require_password' => false,
            'discount_rate' => 0,
            'event_name' => 'trade_payment',
            'refund_event_name' => 'trade_refund',
        ];
        
        return $this->registerMethod(
            $config['name'] ?? 'assets_account',
            \Dybasedev\LunaPrototype\Trade\Payment\Adapters\AssetsAccountPaymentMethod::class,
            array_merge($defaultConfig, $config)
        );
    }
    
    /**
     * 设置支付方式的优先级
     * 
     * @param array<string, int> $priorities
     * @return $this
     */
    public function setPriorities(array $priorities): static
    {
        if ($this->provider) {
            foreach ($priorities as $name => $priority) {
                if ($this->provider->has($name)) {
                    $this->provider->setPriority($name, $priority);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * 启用支付方式
     * 
     * @param string|array $methods
     * @return $this
     */
    public function enable($methods): static
    {
        if ($this->provider) {
            $methods = is_array($methods) ? $methods : [$methods];
            foreach ($methods as $method) {
                if ($this->provider->has($method)) {
                    $this->provider->enable($method);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * 禁用支付方式
     * 
     * @param string|array $methods
     * @return $this
     */
    public function disable($methods): static
    {
        if ($this->provider) {
            $methods = is_array($methods) ? $methods : [$methods];
            foreach ($methods as $method) {
                if ($this->provider->has($method)) {
                    $this->provider->disable($method);
                }
            }
        }
        
        return $this;
    }
}