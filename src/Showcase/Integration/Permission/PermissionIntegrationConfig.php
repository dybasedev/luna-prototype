<?php

namespace Dybasedev\LunaPrototype\Showcase\Integration\Permission;

/**
 * Permission 集成配置
 * 
 * 独立的配置类，提供类型安全和IDE友好的配置
 */
class PermissionIntegrationConfig
{
    /**
     * 是否启用集成
     */
    public bool $enabled = false;
    
    /**
     * 资源命名模式
     * 
     * 使用 {key} 作为占位符，例如 'admin.{key}' 会生成 'admin.users'
     */
    public string $resourcePattern = '{key}';
    
    /**
     * 默认所有者类型字段名
     */
    public string $defaultOwnerTypeField = 'owner_type';
    
    /**
     * 默认所有者ID字段名
     */
    public string $defaultOwnerIdField = 'owner_id';
    
    /**
     * 是否自动检查权限
     */
    public bool $autoCheckPermission = true;
    
    /**
     * 是否启用行级所有者过滤
     */
    public bool $enableOwnerFilter = false;
    
    /**
     * DataTable与权限资源的映射
     * 
     * @var array<string, string>
     */
    public array $resourceMappings = [];
    
    /**
     * 从构建器创建
     */
    public static function fromBuilder(PermissionIntegrationBuilder $builder): static
    {
        $config = new static();
        $config->enabled = $builder->enabled;
        $config->resourcePattern = $builder->resourcePattern;
        $config->defaultOwnerTypeField = $builder->ownerTypeField;
        $config->defaultOwnerIdField = $builder->ownerIdField;
        $config->autoCheckPermission = $builder->autoCheckPermission;
        $config->enableOwnerFilter = $builder->enableOwnerFilter;
        $config->resourceMappings = $builder->resourceMappings;
        return $config;
    }
    
    /**
     * 获取DataTable的权限资源名
     */
    public function getResourceName(string $dataTableKey): string
    {
        if (isset($this->resourceMappings[$dataTableKey])) {
            return $this->resourceMappings[$dataTableKey];
        }
        
        return str_replace('{key}', $dataTableKey, $this->resourcePattern);
    }
}