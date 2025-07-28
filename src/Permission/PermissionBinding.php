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
     * 初始化绑定
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @return void
     */
    public function initialize($app): void
    {
        parent::initialize($app);

        $class = $this->getTargetClass();
        
        if (!$class) {
            return;
        }

        // 添加权限相关的 trait
        if (!in_array(HasPermissions::class, class_uses_recursive($class))) {
            // 注意：在实际使用中，需要手动将 trait 添加到用户模型
            // 这里只是确保接口实现
        }

        // 确保用户模型实现 PermissionSubject 接口
        if (!in_array(PermissionSubject::class, class_implements($class))) {
            // 注意：在实际使用中，需要手动实现接口
        }
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

    /**
     * 获取当前认证用户
     *
     * @return Model|PermissionSubject|null
     */
    public function getCurrentUser(): Model|PermissionSubject|null
    {
        return auth()->user();
    }
}