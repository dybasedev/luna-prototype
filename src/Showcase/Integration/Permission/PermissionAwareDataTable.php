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
            if ($config->enableOwnerFilter) {
                $this->enableOwnerFilter = true;
            }
        }
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
     * 创建记录（添加权限检查）
     */
    public function create(Request $request): mixed
    {
        $this->checkPermission('create', '没有创建权限');
        return parent::create($request);
    }
    
    /**
     * 更新记录（添加权限检查）
     */
    public function update(Request $request): mixed
    {
        $this->checkPermission('update', '没有更新权限');
        $this->checkResourceOwnership($request->input('id'), 'update_all', '没有权限编辑此资源');
        return parent::update($request);
    }
    
    /**
     * 删除记录（添加权限检查）
     */
    public function delete(Request $request): mixed
    {
        $this->checkPermission('delete', '没有删除权限');
        $this->checkResourceOwnership($request->input('id'), 'delete_all', '没有权限删除此资源');
        return parent::delete($request);
    }
    
    /**
     * 批量删除（添加权限检查）
     */
    public function batchDelete(Request $request): int
    {
        $this->checkPermission('delete', '没有删除权限');
        $this->checkBatchResourceOwnership($request->input('ids', []), 'delete_all', '没有权限删除部分资源');
        return parent::batchDelete($request);
    }
    
    /**
     * 导出（添加权限检查）
     */
    public function export(Request $request): mixed
    {
        $this->checkPermission('export', '没有导出权限');
        return parent::export($request);
    }
    
    /**
     * 检查权限
     */
    protected function checkPermission(string $action, string $errorMessage): void
    {
        if (!$this->permissionResource || !PermissionIntegration::isAvailable()) {
            return;
        }
        
        if (!luna_permission()->can($action, $this->permissionResource)) {
            throw \Dybasedev\LunaPrototype\Foundation\Exceptions\LunaException::create('Permission denied')
                ->withDisplayMessage($errorMessage);
        }
    }
    
    /**
     * 检查资源所有权
     */
    protected function checkResourceOwnership($id, string $overridePermission, string $errorMessage): void
    {
        if (!$this->enableOwnerFilter || !$id || !method_exists($this, 'model')) {
            return;
        }
        
        $model = $this->model()::find($id);
        if (!$model || !method_exists($model, 'isOwnedBy')) {
            return;
        }
        
        $holder = PermissionIntegration::getCurrentHolder();
        if (!$holder || $model->isOwnedBy($holder)) {
            return;
        }
        
        // 非所有者需要特殊权限
        if (!luna_permission()->can($overridePermission, $this->permissionResource)) {
            throw \Dybasedev\LunaPrototype\Foundation\Exceptions\LunaException::create('Permission denied')
                ->withDisplayMessage($errorMessage);
        }
    }
    
    /**
     * 检查批量资源所有权
     */
    protected function checkBatchResourceOwnership(array $ids, string $overridePermission, string $errorMessage): void
    {
        if (!$this->enableOwnerFilter || empty($ids) || !method_exists($this, 'model')) {
            return;
        }
        
        $holder = PermissionIntegration::getCurrentHolder();
        if (!$holder) {
            return;
        }
        
        $models = $this->model()::whereIn('id', $ids)->get();
        foreach ($models as $model) {
            if (!method_exists($model, 'isOwnedBy') || $model->isOwnedBy($holder)) {
                continue;
            }
            
            // 发现非所有者的资源，检查特殊权限
            if (!luna_permission()->can($overridePermission, $this->permissionResource)) {
                throw \Dybasedev\LunaPrototype\Foundation\Exceptions\LunaException::create('Permission denied')
                    ->withDisplayMessage($errorMessage);
            }
            break;
        }
    }
    
    /**
     * 获取权限配置
     * 
     * 覆盖父类方法，集成 Permission 组件的权限检查
     * 
     * @param Request $request
     * @return array
     */
    protected function getPermissions(Request $request): array
    {
        // 如果没有配置权限资源，返回默认权限（基于方法存在性）
        if (!$this->permissionResource || !PermissionIntegration::isAvailable()) {
            return parent::getPermissions($request);
        }
        
        // 使用 Permission 组件检查权限
        return [
            'create' => luna_permission()->can('create', $this->permissionResource),
            'update' => luna_permission()->can('update', $this->permissionResource),
            'delete' => luna_permission()->can('delete', $this->permissionResource),
            'export' => luna_permission()->can('export', $this->permissionResource),
            'read' => luna_permission()->can('read', $this->permissionResource),
        ];
    }
    
    /**
     * 获取元数据（包含权限信息）
     */
    public function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        if ($this->permissionResource && PermissionIntegration::isAvailable()) {
            $meta['permission'] = [
                'enabled' => true,
                'resource' => $this->permissionResource,
                'permissions' => $this->getPermissions($request),
            ];
        }
        
        return $meta;
    }
}