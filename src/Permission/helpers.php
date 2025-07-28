<?php

use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\LunaPermission;

if (!function_exists('luna_permission')) {
    /**
     * 获取权限模块实例
     *
     * @return LunaPermission
     */
    function luna_permission(): LunaPermission
    {
        return app('luna.permission');
    }
}

if (!function_exists('luna_permission_can')) {
    /**
     * 检查当前用户是否有权限
     *
     * @param string $action
     * @param string $resource
     * @param array $context
     * @return bool
     */
    function luna_permission_can(string $action, string $resource, array $context = []): bool
    {
        $user = auth()->user();
        
        if (!$user || !$user instanceof PermissionSubject) {
            return false;
        }

        return luna_permission()->check($user, $action, $resource, $context);
    }
}

if (!function_exists('luna_permission_cannot')) {
    /**
     * 检查当前用户是否没有权限
     *
     * @param string $action
     * @param string $resource
     * @param array $context
     * @return bool
     */
    function luna_permission_cannot(string $action, string $resource, array $context = []): bool
    {
        return !luna_permission_can($action, $resource, $context);
    }
}

if (!function_exists('luna_permission_can_any')) {
    /**
     * 检查当前用户是否有任一权限
     *
     * @param array $actions
     * @param string $resource
     * @param array $context
     * @return bool
     */
    function luna_permission_can_any(array $actions, string $resource, array $context = []): bool
    {
        $user = auth()->user();
        
        if (!$user || !$user instanceof PermissionSubject) {
            return false;
        }

        return luna_permission()->checkAny($user, $actions, $resource, $context);
    }
}

if (!function_exists('luna_permission_can_all')) {
    /**
     * 检查当前用户是否有所有权限
     *
     * @param array $actions
     * @param string $resource
     * @param array $context
     * @return bool
     */
    function luna_permission_can_all(array $actions, string $resource, array $context = []): bool
    {
        $user = auth()->user();
        
        if (!$user || !$user instanceof PermissionSubject) {
            return false;
        }

        return luna_permission()->checkAll($user, $actions, $resource, $context);
    }
}

if (!function_exists('luna_permission_register_resource')) {
    /**
     * 注册资源
     *
     * @param string $name
     * @param mixed $definition
     * @return void
     */
    function luna_permission_register_resource(string $name, mixed $definition): void
    {
        luna_permission()->registerResource($name, $definition);
    }
}

if (!function_exists('luna_permission_check')) {
    /**
     * 检查指定主体的权限
     *
     * @param PermissionSubject $subject
     * @param string $action
     * @param string $resource
     * @param array $context
     * @return bool
     */
    function luna_permission_check(
        PermissionSubject $subject,
        string $action,
        string $resource,
        array $context = []
    ): bool {
        return luna_permission()->check($subject, $action, $resource, $context);
    }
}

if (!function_exists('luna_permission_assign_policy')) {
    /**
     * 分配策略给主体
     *
     * @param PermissionSubject $subject
     * @param string $policyName
     * @param array $options
     * @return \Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment
     */
    function luna_permission_assign_policy(
        PermissionSubject $subject,
        string $policyName,
        array $options = []
    ): \Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment {
        return luna_permission()->assignPolicy($subject, $policyName, $options);
    }
}

if (!function_exists('luna_permission_create_policy')) {
    /**
     * 创建权限策略
     *
     * @param string $name
     * @param array $statement
     * @param string|null $description
     * @return \Dybasedev\LunaPrototype\Permission\Models\Policy
     */
    function luna_permission_create_policy(
        string $name,
        array $statement,
        ?string $description = null
    ): \Dybasedev\LunaPrototype\Permission\Models\Policy {
        return luna_permission()->createPolicy($name, $statement, $description);
    }
}