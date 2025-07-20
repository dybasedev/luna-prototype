<?php

namespace Dybasedev\LunaPrototype\Trade\Payment;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Trade\TransactionContext;

/**
 * 支付提供者接口
 * 
 * 管理和提供支付方式，负责支付方式的注册、获取和管理
 * 
 * @package Dybasedev\LunaPrototype\Trade\Payment
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface PaymentProvider
{
    /**
     * 注册支付方式
     * 
     * @param PaymentMethod $paymentMethod
     * @return void
     */
    public function register(PaymentMethod $paymentMethod): void;
    
    /**
     * 批量注册支付方式
     * 
     * @param array<PaymentMethod> $paymentMethods
     * @return void
     */
    public function registerMany(array $paymentMethods): void;
    
    /**
     * 注销支付方式
     * 
     * @param string $name 支付方式名称
     * @return void
     */
    public function unregister(string $name): void;
    
    /**
     * 获取支付方式
     * 
     * @param string $name 支付方式名称
     * @return PaymentMethod|null
     */
    public function get(string $name): ?PaymentMethod;
    
    /**
     * 检查支付方式是否存在
     * 
     * @param string $name 支付方式名称
     * @return bool
     */
    public function has(string $name): bool;
    
    /**
     * 获取所有支付方式
     * 
     * @return array<string, PaymentMethod>
     */
    public function all(): array;
    
    /**
     * 获取可用的支付方式列表
     * 
     * @param SessionHolder $owner 交易所有者
     * @param TransactionContext|null $context 交易上下文
     * @return array<string, PaymentMethod>
     */
    public function getAvailable(SessionHolder $owner, ?TransactionContext $context = null): array;
    
    /**
     * 获取支付方式列表（用于展示）
     * 
     * @param SessionHolder $owner 交易所有者
     * @param TransactionContext|null $context 交易上下文
     * @param bool $onlyAvailable 是否只返回可用的支付方式
     * @return array 返回格式化的支付方式信息
     */
    public function getList(
        SessionHolder $owner,
        ?TransactionContext $context = null,
        bool $onlyAvailable = true
    ): array;
    
    /**
     * 设置默认支付方式
     * 
     * @param string $name 支付方式名称
     * @return void
     */
    public function setDefault(string $name): void;
    
    /**
     * 获取默认支付方式
     * 
     * @return PaymentMethod|null
     */
    public function getDefault(): ?PaymentMethod;
    
    /**
     * 获取默认支付方式名称
     * 
     * @return string|null
     */
    public function getDefaultName(): ?string;
    
    /**
     * 设置支付方式的优先级
     * 
     * @param string $name 支付方式名称
     * @param int $priority 优先级（数字越大优先级越高）
     * @return void
     */
    public function setPriority(string $name, int $priority): void;
    
    /**
     * 获取按优先级排序的支付方式
     * 
     * @return array<string, PaymentMethod>
     */
    public function getSorted(): array;
    
    /**
     * 启用支付方式
     * 
     * @param string $name 支付方式名称
     * @return void
     */
    public function enable(string $name): void;
    
    /**
     * 禁用支付方式
     * 
     * @param string $name 支付方式名称
     * @return void
     */
    public function disable(string $name): void;
    
    /**
     * 检查支付方式是否启用
     * 
     * @param string $name 支付方式名称
     * @return bool
     */
    public function isEnabled(string $name): bool;
    
    /**
     * 获取支付提供者的配置
     * 
     * @return array
     */
    public function getConfiguration(): array;
    
    /**
     * 设置支付提供者的配置
     * 
     * @param array $configuration
     * @return void
     */
    public function setConfiguration(array $configuration): void;
}