<?php

namespace Dybasedev\LunaPrototype\Permission;

use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;

/**
 * 策略构建器
 * 
 * 提供流式 API 来构建权限策略
 * 
 * @example
 * ```php
 * $policy = PolicyBuilder::create('user-management')
 *     ->description('用户管理权限')
 *     ->allow(['create', 'read', 'update', 'delete'])
 *     ->on('users')
 *     ->withCondition('ip_address', ['192.168.1.0/24'])
 *     ->build();
 * ```
 */
class PolicyBuilder
{
    /**
     * 策略名称
     */
    protected(set) string $name;

    /**
     * 策略描述
     */
    protected(set) ?string $description = null;

    /**
     * 策略声明列表
     */
    protected(set) array $statements = [];

    /**
     * 当前正在构建的声明
     */
    protected(set) array $currentStatement = [];

    /**
     * 创建策略构建器
     *
     * @param string $name
     */
    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * 创建新的策略构建器
     *
     * @param string $name
     * @return static
     */
    public static function create(string $name): static
    {
        return new static($name);
    }

    /**
     * 设置策略描述
     *
     * @param string $description
     * @return $this
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 添加允许声明
     *
     * @param string|array $actions
     * @return $this
     */
    public function allow(string|array $actions): static
    {
        $this->finalizeCurrentStatement();
        
        $this->currentStatement = [
            'effect' => PolicyStatement::EFFECT_ALLOW,
            'action' => (array) $actions,
        ];
        
        return $this;
    }

    /**
     * 添加拒绝声明
     *
     * @param string|array $actions
     * @return $this
     */
    public function deny(string|array $actions): static
    {
        $this->finalizeCurrentStatement();
        
        $this->currentStatement = [
            'effect' => PolicyStatement::EFFECT_DENY,
            'action' => (array) $actions,
        ];
        
        return $this;
    }

    /**
     * 设置排除的操作
     *
     * @param string|array $actions
     * @return $this
     */
    public function except(string|array $actions): static
    {
        unset($this->currentStatement['action']);
        $this->currentStatement['not_action'] = (array) $actions;
        return $this;
    }

    /**
     * 设置资源
     *
     * @param string|array $resources
     * @return $this
     */
    public function on(string|array $resources): static
    {
        $this->currentStatement['resource'] = (array) $resources;
        return $this;
    }

    /**
     * 设置主体
     *
     * @param string|array $principals
     * @return $this
     */
    public function for(string|array $principals): static
    {
        $this->currentStatement['principal'] = (array) $principals;
        return $this;
    }

    /**
     * 添加条件
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function withCondition(string $key, mixed $value): static
    {
        if (!isset($this->currentStatement['condition'])) {
            $this->currentStatement['condition'] = [];
        }
        
        $this->currentStatement['condition'][$key] = $value;
        return $this;
    }

    /**
     * 添加多个条件
     *
     * @param array $conditions
     * @return $this
     */
    public function withConditions(array $conditions): static
    {
        if (!isset($this->currentStatement['condition'])) {
            $this->currentStatement['condition'] = [];
        }
        
        $this->currentStatement['condition'] = array_merge(
            $this->currentStatement['condition'],
            $conditions
        );
        
        return $this;
    }

    /**
     * 添加IP地址条件
     *
     * @param string|array $ips
     * @return $this
     */
    public function fromIp(string|array $ips): static
    {
        return $this->withCondition('ip_address', $ips);
    }

    /**
     * 添加时间范围条件
     *
     * @param string $start
     * @param string $end
     * @return $this
     */
    public function betweenTime(string $start, string $end): static
    {
        return $this->withCondition('time_range', [
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * 添加日期范围条件
     *
     * @param string $start
     * @param string $end
     * @return $this
     */
    public function betweenDate(string $start, string $end): static
    {
        return $this->withCondition('date_range', [
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * 完成当前声明的构建
     *
     * @return void
     */
    protected function finalizeCurrentStatement(): void
    {
        if (!empty($this->currentStatement)) {
            $this->statements[] = $this->currentStatement;
            $this->currentStatement = [];
        }
    }

    /**
     * 构建策略
     *
     * @return Policy
     */
    public function build(): Policy
    {
        $this->finalizeCurrentStatement();
        
        if (empty($this->statements)) {
            throw new \InvalidArgumentException('Policy must have at least one statement');
        }

        // 多个声明时作为数组传递
        $statement = count($this->statements) === 1 
            ? $this->statements[0] 
            : $this->statements;

        $policy = Policy::create([
            'name' => $this->name,
            'description' => $this->description,
            'current_version_id' => '',
        ]);

        $policy->createVersion($statement);
        
        return $policy;
    }

    /**
     * 获取声明数组（不创建策略）
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->finalizeCurrentStatement();
        
        return count($this->statements) === 1 
            ? $this->statements[0] 
            : ['statements' => $this->statements];
    }

    /**
     * 创建简单的 CRUD 策略
     *
     * @param string $name
     * @param string $resource
     * @param string|null $description
     * @return Policy
     */
    public static function crud(string $name, string $resource, ?string $description = null): Policy
    {
        return static::create($name)
            ->description($description ?? "CRUD permissions for {$resource}")
            ->allow(['create', 'read', 'update', 'delete', 'list'])
            ->on($resource)
            ->build();
    }

    /**
     * 创建只读策略
     *
     * @param string $name
     * @param string $resource
     * @param string|null $description
     * @return Policy
     */
    public static function readOnly(string $name, string $resource, ?string $description = null): Policy
    {
        return static::create($name)
            ->description($description ?? "Read-only permissions for {$resource}")
            ->allow(['read', 'list'])
            ->on($resource)
            ->build();
    }

    /**
     * 创建管理员策略
     *
     * @param string $name
     * @param string|null $description
     * @return Policy
     */
    public static function admin(string $name, ?string $description = null): Policy
    {
        return static::create($name)
            ->description($description ?? "Full administrative permissions")
            ->allow('*')
            ->on('*')
            ->build();
    }
}