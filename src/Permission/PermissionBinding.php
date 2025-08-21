<?php

namespace Dybasedev\LunaPrototype\Permission;

use Dybasedev\LunaPrototype\Foundation\SessionHolderBinding;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Model;

/**
 * 权限绑定类
 * 
 * 用于将权限功能绑定到用户模型
 */
class PermissionBinding extends SessionHolderBinding
{
    /**
     * 绑定标识符
     */
    protected(set) ?string $identifier = null {
        get {
            return $this->identifier;
        }
    }

    /**
     * 绑定描述
     */
    protected(set) ?string $description = null {
        get {
            return $this->description;
        }
    }

    /**
     * 构造函数
     *
     * @param string $owner 绑定的用户模型类
     * @param string|null $identifier 绑定标识符（如：'user', 'admin', 'api_client'）
     * @throws \RuntimeException 当模型未实现必要的接口时抛出异常
     */
    public function __construct(string $owner, ?string $identifier = null)
    {
        parent::__construct($owner);
        
        // 设置标识符，如果未提供则使用类名的简短形式
        $this->identifier = $identifier ?: strtolower(class_basename($owner));
        
        // 自动设置 table 为 owner
        $this->table($owner);
        
        // 验证模型是否实现了必要的接口和 trait
        $this->validateTargetClass();
    }

    /**
     * 验证目标类是否满足权限系统的要求
     *
     * @return void
     * @throws \RuntimeException
     */
    protected function validateTargetClass(): void
    {
        $class = $this->getTargetClass();
        
        if (!$class) {
            return;
        }

        // 确保用户模型实现 PermissionSubject 接口
        if (!in_array(PermissionSubject::class, class_implements($class))) {
            throw new \RuntimeException(
                sprintf('Model %s must implement %s interface', $class, PermissionSubject::class)
            );
        }

        // 检查是否使用了 HasPermissions trait（仅警告，不强制）
        if (!in_array(HasPermissions::class, class_uses_recursive($class))) {
            // 可以通过日志记录警告，但不抛出异常
            // 因为用户可能选择自己实现接口方法
        }
    }

    /**
     * 设置描述
     *
     * @param string $description
     * @return static
     */
    public function withDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 获取绑定的用户模型实例
     *
     * @param mixed $id
     * @return Model|PermissionSubject|null
     */
    public function getUser(mixed $id): Model|PermissionSubject|null
    {
        $class = $this->getTargetClass();
        
        if (!$class) {
            return null;
        }

        return $class::query()->find($id);
    }
}