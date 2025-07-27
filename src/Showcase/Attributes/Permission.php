<?php

namespace Dybasedev\LunaPrototype\Showcase\Attributes;

use Attribute;

/**
 * 权限配置注解
 * 
 * 用于配置 DataTable 的权限要求
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Permission
{
    /**
     * 构造函数
     * 
     * @param string|array $permissions 权限名称或权限数组
     * @param string $guard 守卫名称
     * @param bool $requireAll 是否需要所有权限
     */
    public function __construct(
        public string|array $permissions,
        public string $guard = 'web',
        public bool $requireAll = false
    ) {
    }
    
    /**
     * 获取权限数组
     * 
     * @return array
     */
    public function getPermissions(): array
    {
        return is_array($this->permissions) ? $this->permissions : [$this->permissions];
    }
}