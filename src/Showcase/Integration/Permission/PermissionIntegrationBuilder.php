<?php

namespace Dybasedev\LunaPrototype\Showcase\Integration\Permission;

/**
 * Permission 集成配置构建器
 */
class PermissionIntegrationBuilder
{
    protected(set) string $resourcePattern = '{key}';
    protected(set) string $ownerTypeField = 'owner_type';
    protected(set) string $ownerIdField = 'owner_id';
    protected(set) bool $autoCheckPermission = true;
    protected(set) bool $enableOwnerFilter = false;
    protected(set) array $resourceMappings = [];
    
    /**
     * 创建新的构建器实例
     * 
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * 设置资源命名模式
     */
    public function withResourcePattern(string $pattern): static
    {
        $this->resourcePattern = $pattern;
        return $this;
    }
    
    /**
     * 设置所有者字段
     */
    public function withOwnerFields(string $typeField, string $idField): static
    {
        $this->ownerTypeField = $typeField;
        $this->ownerIdField = $idField;
        return $this;
    }
    
    /**
     * 启用所有者过滤
     */
    public function enableOwnerFilter(): static
    {
        $this->enableOwnerFilter = true;
        return $this;
    }
    
    /**
     * 禁用自动权限检查
     */
    public function disableAutoCheck(): static
    {
        $this->autoCheckPermission = false;
        return $this;
    }
    
    /**
     * 映射DataTable到权限资源
     */
    public function mapResource(string $dataTableKey, string $permissionResource): static
    {
        $this->resourceMappings[$dataTableKey] = $permissionResource;
        return $this;
    }
    
    /**
     * 构建配置对象
     */
    public function build(): PermissionIntegrationConfig
    {
        return PermissionIntegrationConfig::fromBuilder($this);
    }
}