<?php

use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegration;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;

if (!function_exists('luna_showcase_check_permission')) {
    /**
     * 检查 DataTable 权限
     * 
     * @param string $dataTableKey DataTable 的键名
     * @param string $action 要检查的操作，默认为 'read'
     * @return bool
     */
    function luna_showcase_check_permission(string $dataTableKey, string $action = 'read'): bool
    {
        $configure = luna_module_configure(LunaShowcaseConfigure::class);
        
        if (!$configure->isPermissionIntegrationEnabled) {
            return true;
        }
        
        $resource = $configure->permissionConfig->getResourceName($dataTableKey);
        return PermissionIntegration::checkAccess($resource, $action);
    }
}

if (!function_exists('luna_showcase_can_operate')) {
    /**
     * 检查是否可以操作资源
     * 
     * @param mixed $resource 资源对象
     * @param string $action 要执行的操作
     * @param string $permissionResource 权限资源名称
     * @return bool
     */
    function luna_showcase_can_operate($resource, string $action, string $permissionResource): bool
    {
        return PermissionIntegration::canOperateResource(
            $resource,
            $action,
            $permissionResource
        );
    }
}

if (!function_exists('luna_showcase_filter_columns')) {
    /**
     * 根据权限过滤列
     * 
     * @param array $columns 列配置数组
     * @param string $resource 权限资源名称
     * @param array $columnPermissions 列权限映射
     * @return array
     */
    function luna_showcase_filter_columns(array $columns, string $resource, array $columnPermissions = []): array
    {
        return PermissionIntegration::filterVisibleColumns(
            $columns,
            $resource,
            $columnPermissions
        );
    }
}