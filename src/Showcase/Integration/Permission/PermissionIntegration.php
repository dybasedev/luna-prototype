<?php

namespace Dybasedev\LunaPrototype\Showcase\Integration\Permission;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Permission 组件集成
 */
class PermissionIntegration
{
    /**
     * 检查 Permission 组件是否可用
     */
    public static function isAvailable(): bool
    {
        try {
            return luna_permission() !== null;
        } catch (\Throwable) {
            return false;
        }
    }
    
    /**
     * 获取当前会话持有者
     */
    public static function getCurrentHolder(): ?SessionHolder
    {
        // 从Permission组件获取当前绑定的用户
        $configure = luna_module_configure(LunaPermissionConfigure::class);
        
        foreach ($configure->getBindings() as $binding) {
            if ($holder = $binding->getCurrentUser()) {
                if ($holder instanceof SessionHolder) {
                    return $holder;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 检查DataTable访问权限
     */
    public static function checkAccess(string $resource, string $action = 'read'): bool
    {
        if (!self::isAvailable()) {
            return true;
        }
        
        return luna_permission()->can($action, $resource);
    }
    
    /**
     * 应用所有者过滤
     */
    public static function applyOwnerFilter(
        Builder $query,
        string $resource,
        PermissionIntegrationConfig $config
    ): Builder {
        if (!self::isAvailable()) {
            return $query;
        }
        
        // 检查是否有查看所有记录的权限
        if (luna_permission()->can('view_all', $resource)) {
            return $query;
        }
        
        // 获取当前会话持有者
        $holder = self::getCurrentHolder();
        if (!$holder) {
            // 没有会话持有者时，返回空结果
            return $query->whereRaw('1 = 0');
        }
        
        // 应用所有者过滤
        $query->where($config->defaultOwnerTypeField, $holder->getOperatorType())
              ->where($config->defaultOwnerIdField, $holder->getOperatorId());
        
        return $query;
    }
    
    /**
     * 检查资源操作权限
     */
    public static function canOperateResource(
        $resource,
        string $action,
        string $permissionResource
    ): bool {
        if (!self::isAvailable()) {
            return true;
        }
        
        $context = [];
        
        // 如果资源使用了 HasOwner trait
        if (method_exists($resource, 'getResourcePermissionContext')) {
            $context = $resource->getResourcePermissionContext();
            
            // 添加当前会话持有者信息
            if ($holder = self::getCurrentHolder()) {
                $context['current_holder_type'] = $holder->getOperatorType();
                $context['current_holder_id'] = $holder->getOperatorId();
            }
        }
        
        return luna_permission()->can($action, $permissionResource, $context);
    }
    
    /**
     * 过滤可见列
     */
    public static function filterVisibleColumns(
        array $columns,
        string $resource,
        array $columnPermissions = []
    ): array {
        if (!self::isAvailable() || empty($columnPermissions)) {
            return $columns;
        }
        
        return array_filter($columns, function($column) use ($resource, $columnPermissions) {
            $columnName = is_object($column) && property_exists($column, 'name') 
                ? $column->name 
                : ($column['name'] ?? '');
            
            if (!isset($columnPermissions[$columnName])) {
                return true;
            }
            
            $permission = $columnPermissions[$columnName];
            if (is_string($permission)) {
                return luna_permission()->can($permission, $resource);
            }
            
            if (is_array($permission)) {
                $action = $permission['action'] ?? 'view';
                $res = $permission['resource'] ?? $resource;
                return luna_permission()->can($action, $res);
            }
            
            return true;
        });
    }
}