<?php

namespace Dybasedev\LunaPrototype\Permission\Installations;

use Dybasedev\LunaPrototype\Permission\PolicyBuilder;

/**
 * 权限系统默认安装器
 * 
 * 初始化内置的角色和策略
 */
class PermissionInstallation extends BasePermissionInstallation
{
    /**
     * 执行安装
     *
     * @return void
     */
    public function install(): void
    {
        $this->writeln('=> Installing Luna Permission system...');

        // 创建内置角色
        $this->installBuiltInRoles();

        // 创建内置策略
        $this->installBuiltInPolicies();

        $this->writeln('Luna Permission system installed successfully!');
    }

    /**
     * 安装内置角色
     *
     * @return void
     */
    protected function installBuiltInRoles(): void
    {
        $this->writeln('Creating built-in roles...');
        $this->createRoles($this->getBuiltInRoles());
    }

    /**
     * 安装内置策略
     *
     * @return void
     */
    protected function installBuiltInPolicies(): void
    {
        $this->writeln('Creating built-in policies...');

        // 管理员完全访问权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('admin-full-access')
                ->description('管理员完全访问权限')
                ->allow('*')
                ->on('*')
        );

        // 用户基本访问权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('user-basic-access')
                ->description('用户基本访问权限')
                ->allow(['read', 'list'])
                ->on(['users:self', 'profile:*'])
        );

        // API 客户端访问权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('api-access')
                ->description('API 客户端访问权限')
                ->allow(['read', 'list', 'create', 'update'])
                ->on('api.*')
                ->deny(['delete'])
                ->on('api.*')
        );

        // 只读访问权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('read-only-access')
                ->description('只读访问权限')
                ->allow(['read', 'list'])
                ->on('*')
                ->deny('*')
                ->on(['system.*', 'admin.*'])
        );
    }

    /**
     * 获取内置角色定义
     *
     * @return array
     */
    protected function getBuiltInRoles(): array
    {
        return [
            [
                'name' => 'super-admin',
                'display_name' => '超级管理员',
                'description' => '拥有系统所有权限的超级管理员',
                'is_system' => true,
            ],
            [
                'name' => 'system',
                'display_name' => '系统',
                'description' => '系统内部使用的角色',
                'is_system' => true,
            ],
            [
                'name' => 'api-client',
                'display_name' => 'API 客户端',
                'description' => '第三方 API 客户端',
                'is_system' => true,
            ],
        ];
    }

}