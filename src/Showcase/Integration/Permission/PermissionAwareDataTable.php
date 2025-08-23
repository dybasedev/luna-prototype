<?php

namespace Dybasedev\LunaPrototype\Showcase\Integration\Permission;

use Dybasedev\LunaPrototype\Showcase\DataTable\DataTable;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * 权限感知的 DataTable
 * 
 * 提供权限集成功能
 *
 * @mixin DataTable
 */
trait PermissionAwareDataTable
{
    /**
     * 权限资源名称
     */
    protected ?string $permissionResource = null;
    
    /**
     * 列权限映射
     */
    protected array $columnPermissions = [];
    
    /**
     * 是否启用所有者过滤
     */
    protected bool $enableOwnerFilter = false;
    
    /**
     * 初始化权限配置
     */
    protected function initializePermissionAwareDataTable(): void
    {
        $configure = luna_module_configure(\Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure::class);
        
        if ($configure->isPermissionIntegrationEnabled) {
            $config = $configure->permissionConfig;
            
            // 自动设置资源名
            if (!$this->permissionResource && property_exists($this, 'dataTableKey')) {
                $this->permissionResource = $config->getResourceName($this->dataTableKey);
            }
            
            // 设置默认配置
            $this->enableOwnerFilter = $config->enableOwnerFilter;
        }
        
        $this->configurePermissions();
    }
    
    /**
     * 配置权限（子类覆盖）
     */
    protected function configurePermissions(): void
    {
        // 子类实现
    }
    
    /**
     * 权限验证
     */
    public function authorized(): bool
    {
        if (!$this->permissionResource) {
            return parent::authorized();
        }
        
        return PermissionIntegration::checkAccess($this->permissionResource, 'read');
    }
    
    /**
     * 构建查询（应用所有者过滤）
     */
    public function query(Request $request): Builder
    {
        $query = $this->buildQuery($request);
        
        if ($this->permissionResource && $this->enableOwnerFilter) {
            $configure = luna_module_configure(\Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure::class);
            if ($config = $configure->permissionConfig) {
                $query = PermissionIntegration::applyOwnerFilter(
                    $query,
                    $this->permissionResource,
                    $config
                );
            }
        }
        
        return $query;
    }
    
    /**
     * 获取列配置（应用权限过滤）
     */
    public function columns(Request $request): array
    {
        $columns = $this->defineColumns($request);
        
        if ($this->permissionResource && !empty($this->columnPermissions)) {
            $columns = PermissionIntegration::filterVisibleColumns(
                $columns,
                $this->permissionResource,
                $this->columnPermissions
            );
        }
        
        return $columns;
    }
    
    /**
     * 构建基础查询（子类实现）
     */
    abstract protected function buildQuery(Request $request): Builder;
    
    /**
     * 定义列（子类实现）
     */
    abstract protected function defineColumns(Request $request): array;
    
    /**
     * 获取操作按钮（应用权限过滤）
     */
    protected function getActions(Request $request): array
    {
        $actions = $this->defineActions($request);
        
        if (!$this->permissionResource || !PermissionIntegration::isAvailable()) {
            return $actions;
        }
        
        // 过滤操作按钮
        return array_filter($actions, function($action) {
            $key = $action['key'] ?? '';
            
            // 默认操作映射
            $defaultMapping = [
                'create' => 'create',
                'edit' => 'update',
                'update' => 'update',
                'delete' => 'delete',
                'view' => 'read',
                'export' => 'export',
            ];
            
            if (isset($defaultMapping[$key])) {
                return luna_permission()->can($defaultMapping[$key], $this->permissionResource);
            }
            
            return true;
        });
    }
    
    /**
     * 定义操作按钮（子类覆盖）
     */
    protected function defineActions(Request $request): array
    {
        return [];
    }
    
    /**
     * 获取批量操作（应用权限过滤）
     */
    protected function getBatchActions(Request $request): array
    {
        $actions = $this->defineBatchActions($request);
        
        if (!$this->permissionResource || !PermissionIntegration::isAvailable()) {
            return $actions;
        }
        
        // 过滤批量操作
        return array_filter($actions, function($action) {
            $key = $action['key'] ?? '';
            
            // 默认操作映射
            $defaultMapping = [
                'delete' => 'delete',
                'export' => 'export',
                'approve' => 'update',
                'reject' => 'update',
            ];
            
            if (isset($defaultMapping[$key])) {
                return luna_permission()->can($defaultMapping[$key], $this->permissionResource);
            }
            
            return true;
        });
    }
    
    /**
     * 定义批量操作（子类覆盖）
     */
    protected function defineBatchActions(Request $request): array
    {
        return [];
    }
    
    /**
     * 获取元数据（包含权限信息）
     */
    public function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        if ($this->permissionResource && PermissionIntegration::isAvailable()) {
            // 生成权限元数据
            $permissions = [];
            $actions = ['create', 'read', 'update', 'delete', 'export'];
            
            foreach ($actions as $action) {
                $permissions[$action] = luna_permission()->can($action, $this->permissionResource);
            }
            
            $meta['permission'] = [
                'enabled' => true,
                'resource' => $this->permissionResource,
                'permissions' => $permissions,
            ];
        }
        
        return $meta;
    }
}