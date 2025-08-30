<?php

namespace Dybasedev\LunaPrototype\Permission\Traits;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;

/**
 * 资源所有者 Trait
 * 
 * 为资源提供所有者关系，用于权限控制中的资源所有权判断
 */
trait HasOwner
{
    /**
     * 获取所有者类型字段名
     */
    public function getOwnerTypeKeyName(): string
    {
        return $this->ownerTypeKeyName ?? 'owner_type';
    }
    
    /**
     * 获取所有者ID字段名
     */
    public function getOwnerIdKeyName(): string
    {
        return $this->ownerIdKeyName ?? 'owner_id';
    }
    
    /**
     * 获取所有者类型
     */
    public function getOwnerType(): ?int
    {
        $field = $this->getOwnerTypeKeyName();
        return $this->$field ?? null;
    }
    
    /**
     * 获取所有者ID
     */
    public function getOwnerId(): mixed
    {
        $field = $this->getOwnerIdKeyName();
        return $this->$field ?? null;
    }
    
    /**
     * 检查是否为指定持有者所有
     */
    public function isOwnedBy(SessionHolder|int|string $holder, ?int $type = null): bool
    {
        if ($holder instanceof SessionHolder) {
            $holderId = $holder->getOperatorId();
            $holderType = $holder->getOperatorType();
        } else {
            $holderId = $holder;
            $holderType = $type;
        }
        
        // 比较ID
        if ($this->getOwnerId() != $holderId) {
            return false;
        }
        
        // 如果提供了类型，也要比较类型
        if ($holderType !== null && $this->getOwnerType() !== null) {
            return $this->getOwnerType() == $holderType;
        }
        
        return true;
    }
    
    /**
     * 设置所有者
     */
    public function setOwner(SessionHolder $holder): static
    {
        $this->{$this->getOwnerTypeKeyName()} = $holder->getOperatorType();
        $this->{$this->getOwnerIdKeyName()} = $holder->getOperatorId();
        return $this;
    }
    
    /**
     * 获取资源权限上下文
     */
    public function getResourcePermissionContext(): array
    {
        $context = [
            'resource_owner_id' => $this->getOwnerId(),
            'resource_owner_type' => $this->getOwnerType(),
        ];
        
        // 如果有主键，添加资源ID
        if (method_exists($this, 'getKey')) {
            $context['resource_id'] = $this->getKey();
        }
        
        // 添加额外的权限属性 - 直接合并到上下文中
        if (method_exists($this, 'getPermissionAttributes')) {
            $context = array_merge($context, $this->getPermissionAttributes());
        }
        
        return $context;
    }
}