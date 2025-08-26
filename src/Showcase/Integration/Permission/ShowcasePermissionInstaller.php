<?php

namespace Dybasedev\LunaPrototype\Showcase\Integration\Permission;

use Dybasedev\LunaPrototype\Permission\Installations\BasePermissionInstallation;
use Dybasedev\LunaPrototype\Permission\PolicyBuilder;

/**
 * Showcase Permission 集成预设安装器
 * 
 * 为 Showcase 组件的 Permission 集成提供预设的策略定义，
 * 这些策略是使 DataTable 权限控制正常工作的基础。
 * 
 * 注意：这是框架级别的预设，业务系统可以根据需要覆盖或扩展这些策略。
 */
class ShowcasePermissionInstaller extends BasePermissionInstallation
{
    /**
     * 前置依赖的安装器列表
     * 
     * @var class-string<Installation>[]
     */
    protected array $installations = [
        \Dybasedev\LunaPrototype\Permission\Installations\PermissionInstallation::class,
    ];

    /**
     * 执行安装
     *
     * @return void
     */
    public function install(): void
    {
        $this->writeln('=> Installing Showcase Permission integration presets...');
        
        // 创建 DataTable 通用操作策略
        $this->installDataTablePolicies();
        
        // 创建示例角色（可选）
        $this->installExampleRoles();
        
        $this->writeln('Showcase Permission integration presets installed successfully!');
    }

    /**
     * 安装 DataTable 通用操作策略
     * 
     * 这些策略定义了对 DataTable 资源的标准 CRUD 操作权限
     */
    protected function installDataTablePolicies(): void
    {
        $this->writeln('Creating DataTable operation policies...');
        
        // DataTable 完全管理权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-admin')
                ->description('DataTable 完全管理权限')
                ->allow('*')
                ->on('datatable.*')
        );
        
        // DataTable 标准 CRUD 权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-crud')
                ->description('DataTable 标准 CRUD 权限')
                ->allow(['create', 'read', 'update', 'delete', 'list', 'export'])
                ->on('datatable.*')
        );
        
        // DataTable 只读权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-readonly')
                ->description('DataTable 只读权限')
                ->allow(['read', 'list'])
                ->on('datatable.*')
                ->deny(['create', 'update', 'delete', 'export'])
                ->on('datatable.*')
        );
        
        // DataTable 数据编辑权限（不含删除）
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-editor')
                ->description('DataTable 数据编辑权限')
                ->allow(['read', 'list', 'create', 'update', 'export'])
                ->on('datatable.*')
                ->deny(['delete'])
                ->on('datatable.*')
        );
        
        // DataTable 自有数据管理权限
        // 这个策略配合 enableOwnerFilter 使用，只允许管理自己创建的数据
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-owner')
                ->description('DataTable 自有数据管理权限')
                ->allow(['create', 'read', 'list'])
                ->on('datatable.*')
                ->allow(['update', 'delete'])
                ->on('datatable.*')
                ->withCondition('owner', true)  // 需要是资源所有者
        );
        
        // DataTable 导出权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-export')
                ->description('DataTable 数据导出权限')
                ->allow(['export'])
                ->on('datatable.*')
        );
        
        // 为具体的 DataTable 资源创建细粒度策略示例
        // 业务系统可以参考此模式创建自己的策略
        $this->createExampleDataTablePolicies();
    }
    
    /**
     * 创建示例 DataTable 策略
     * 
     * 这些是更具体的策略示例，展示如何为特定的 DataTable 创建权限
     */
    protected function createExampleDataTablePolicies(): void
    {
        // 用户管理 DataTable 策略
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-users-admin')
                ->description('用户管理 DataTable 完全权限')
                ->allow('*')
                ->on('datatable.users')
        );
        
        // 内容管理 DataTable 策略  
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-content-editor')
                ->description('内容管理 DataTable 编辑权限')
                ->allow(['read', 'list', 'create', 'update'])
                ->on(['datatable.posts', 'datatable.pages', 'datatable.categories'])
                ->deny(['delete'])
                ->on(['datatable.posts', 'datatable.pages'])
        );
        
        // 财务数据 DataTable 策略
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-finance-viewer')
                ->description('财务 DataTable 只读权限')
                ->allow(['read', 'list', 'export'])
                ->on(['datatable.orders', 'datatable.transactions', 'datatable.invoices'])
                ->deny(['create', 'update', 'delete'])
                ->on(['datatable.transactions', 'datatable.invoices'])
        );
    }
    
    /**
     * 安装示例角色
     * 
     * 创建一些预设角色，展示如何组合使用上述策略
     */
    protected function installExampleRoles(): void
    {
        $this->writeln('Creating example DataTable roles...');
        
        // DataTable 管理员角色
        $adminRole = $this->createRole(
            'datatable-admin',
            'DataTable 管理员',
            '拥有所有 DataTable 的完全管理权限',
            false
        );
        
        // DataTable 编辑员角色
        $editorRole = $this->createRole(
            'datatable-editor',
            'DataTable 编辑员',
            '可以创建和编辑 DataTable 数据，但不能删除',
            false
        );
        
        // DataTable 查看者角色
        $viewerRole = $this->createRole(
            'datatable-viewer', 
            'DataTable 查看者',
            '只能查看 DataTable 数据',
            false
        );
        
        // 为角色附加策略（如果 Role 模型支持）
        if ($adminRole && method_exists($adminRole, 'attachPolicy')) {
            $adminRole->attachPolicy('datatable-admin');
        }
        
        if ($editorRole && method_exists($editorRole, 'attachPolicy')) {
            $editorRole->attachPolicy('datatable-editor');
        }
        
        if ($viewerRole && method_exists($viewerRole, 'attachPolicy')) {
            $viewerRole->attachPolicy('datatable-readonly');
        }
    }
    
    /**
     * 创建高级权限策略
     * 
     * 这些策略展示了更复杂的权限场景
     */
    protected function installAdvancedPolicies(): void
    {
        // 基于时间的权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-business-hours')
                ->description('工作时间 DataTable 访问权限')
                ->allow(['read', 'list', 'create', 'update'])
                ->on('datatable.*')
                ->withCondition('time_range', ['09:00', '18:00'])
                ->withCondition('weekdays', ['mon', 'tue', 'wed', 'thu', 'fri'])
        );
        
        // 基于 IP 的权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-internal-network')
                ->description('内网 DataTable 访问权限')
                ->allow('*')
                ->on('datatable.*')
                ->withCondition('ip_range', ['192.168.0.0/16', '10.0.0.0/8'])
        );
        
        // 分级权限 - 敏感数据列
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-sensitive-columns')
                ->description('DataTable 敏感列查看权限')
                ->allow(['view_email', 'view_phone', 'view_financial'])
                ->on('datatable.*')
        );
        
        // 批量操作权限
        $this->createPolicyFromBuilder(
            PolicyBuilder::create('datatable-batch-operations')
                ->description('DataTable 批量操作权限')
                ->allow(['batch_update', 'batch_delete', 'batch_export'])
                ->on('datatable.*')
        );
    }
}