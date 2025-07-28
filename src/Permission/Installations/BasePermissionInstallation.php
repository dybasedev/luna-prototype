<?php

namespace Dybasedev\LunaPrototype\Permission\Installations;

use Dybasedev\LunaPrototype\Foundation\Installation;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Permission\PolicyBuilder;

/**
 * 权限安装器基类
 * 
 * 提供权限系统初始化的基础功能，业务端可以继承此类进行扩展
 */
abstract class BasePermissionInstallation extends Installation
{
    /**
     * 创建角色
     *
     * @param string $name
     * @param string $displayName
     * @param string|null $description
     * @param bool $isSystem
     * @param bool $skipIfExists
     * @return Role|null
     */
    protected function createRole(
        string $name,
        string $displayName,
        ?string $description = null,
        bool $isSystem = false,
        bool $skipIfExists = true
    ): ?Role {
        $existingRole = Role::findByName($name);

        if ($existingRole && $skipIfExists) {
            $this->writeln("Role '{$name}' already exists, skipping", 'v');
            return $existingRole;
        }

        if ($existingRole && !$skipIfExists) {
            $existingRole->delete();
            $this->writeln("Role '{$name}' deleted for recreation", 'vv');
        }

        $role = Role::create([
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description,
            'is_system' => $isSystem,
        ]);

        $this->writeln("Role '{$name}' created", 'v');
        return $role;
    }

    /**
     * 使用 PolicyBuilder 创建策略
     *
     * @param PolicyBuilder $builder
     * @param bool $skipIfExists
     * @return Policy|null
     */
    protected function createPolicyFromBuilder(PolicyBuilder $builder, bool $skipIfExists = true): ?Policy
    {
        // 先构建数组以获取策略名称
        $policyData = $builder->toArray();
        $policyName = $this->extractPolicyName($builder);

        if (!$policyName) {
            $this->writeln("Cannot determine policy name from builder", 'vv');
            return null;
        }

        $existingPolicy = Policy::findByName($policyName);

        if ($existingPolicy && $skipIfExists) {
            $this->writeln("Policy '{$policyName}' already exists, skipping", 'v');
            return $existingPolicy;
        }

        if ($existingPolicy && !$skipIfExists) {
            $existingPolicy->delete();
            $this->writeln("Policy '{$policyName}' deleted for recreation", 'vv');
        }

        try {
            $policy = $builder->build();
            $this->writeln("Policy '{$policyName}' created", 'v');
            return $policy;
        } catch (\Exception $e) {
            $this->writeln("Failed to create policy '{$policyName}': " . $e->getMessage(), 'vv');
            return null;
        }
    }

    /**
     * 创建策略
     *
     * @param string $name
     * @param array $statement
     * @param string|null $description
     * @param bool $skipIfExists
     * @return Policy|null
     */
    protected function createPolicy(
        string $name,
        array $statement,
        ?string $description = null,
        bool $skipIfExists = true
    ): ?Policy {
        $existingPolicy = Policy::findByName($name);

        if ($existingPolicy && $skipIfExists) {
            $this->writeln("Policy '{$name}' already exists, skipping", 'v');
            return $existingPolicy;
        }

        if ($existingPolicy && !$skipIfExists) {
            $existingPolicy->delete();
            $this->writeln("Policy '{$name}' deleted for recreation", 'vv');
        }

        try {
            $policy = Policy::create([
                'name' => $name,
                'description' => $description,
                'current_version' => '',
            ]);

            $policy->createVersion($statement);
            
            $this->writeln("Policy '{$name}' created", 'v');
            return $policy;
        } catch (\Exception $e) {
            $this->writeln("Failed to create policy '{$name}': " . $e->getMessage(), 'vv');
            return null;
        }
    }

    /**
     * 批量创建角色
     *
     * @param array $roles
     * @param bool $skipIfExists
     * @return void
     */
    protected function createRoles(array $roles, bool $skipIfExists = true): void
    {
        foreach ($roles as $roleData) {
            $this->createRole(
                $roleData['name'],
                $roleData['display_name'],
                $roleData['description'] ?? null,
                $roleData['is_system'] ?? false,
                $skipIfExists
            );
        }
    }

    /**
     * 从 PolicyBuilder 提取策略名称
     *
     * @param PolicyBuilder $builder
     * @return string|null
     */
    private function extractPolicyName(PolicyBuilder $builder): ?string
    {
        // 通过反射获取 builder 的 name 属性
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('name');
        $property->setAccessible(true);
        return $property->getValue($builder);
    }
}