<?php

namespace Dybasedev\LunaPrototype\Permission\Support;

use Dybasedev\LunaPrototype\Permission\LunaPermission;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;

/**
 * 权限检查器
 * 
 * 提供流畅的接口来进行权限检查，简化业务端的使用
 */
class PermissionChecker
{
    use Macroable;

    /**
     * 权限主体
     */
    protected ?PermissionSubject $subject = null;

    /**
     * 上下文数据
     */
    protected array $context = [];

    /**
     * Luna 权限服务
     */
    protected LunaPermission $permission;

    /**
     * 构造函数
     */
    public function __construct(?PermissionSubject $subject = null)
    {
        $this->permission = app('luna.permission');
        $this->subject = $subject;
    }

    /**
     * 设置权限主体
     *
     * @param PermissionSubject|null $subject
     * @return static
     */
    public function for(?PermissionSubject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * 设置当前用户作为主体
     *
     * @param string|null $guard
     * @return static
     */
    public function forCurrentUser(?string $guard = null): static
    {
        $user = $guard ? auth()->guard($guard)->user() : auth()->user();
        
        if ($user instanceof PermissionSubject) {
            $this->subject = $user;
        }
        
        return $this;
    }

    /**
     * 设置资源所有者
     *
     * @param mixed $owner
     * @return static
     */
    public function ownedBy($owner): static
    {
        if ($owner instanceof Model) {
            $this->context['resource_owner'] = $owner->getKey();
        } else {
            $this->context['resource_owner'] = $owner;
        }
        
        return $this;
    }

    /**
     * 设置资源为当前用户所有
     *
     * @return static
     */
    public function ownedBySelf(): static
    {
        $this->context['resource_owner'] = '@self';
        
        if ($this->subject) {
            $this->context['current_user'] = $this->subject->getSubjectId();
        }
        
        return $this;
    }

    /**
     * 设置资源ID
     *
     * @param mixed $resourceId
     * @return static
     */
    public function onResource($resourceId): static
    {
        if ($resourceId instanceof Model) {
            $this->context['resource_id'] = $resourceId->getKey();
            
            // 自动提取常用属性
            $this->withResourceModel($resourceId);
        } else {
            $this->context['resource_id'] = $resourceId;
        }
        
        return $this;
    }

    /**
     * 设置资源属性
     *
     * @param string $attribute
     * @param mixed $value
     * @return static
     */
    public function where(string $attribute, $value): static
    {
        $this->context['resource_attributes'][$attribute] = $value;
        return $this;
    }

    /**
     * 批量设置资源属性
     *
     * @param array $attributes
     * @return static
     */
    public function withAttributes(array $attributes): static
    {
        $this->context['resource_attributes'] = array_merge(
            $this->context['resource_attributes'] ?? [],
            $attributes
        );
        
        return $this;
    }

    /**
     * 从模型自动提取资源信息
     *
     * @param Model $model
     * @return static
     */
    public function withResourceModel(Model $model): static
    {
        // 尝试提取所有者信息
        foreach (['user_id', 'owner_id', 'created_by'] as $field) {
            if (isset($model->{$field})) {
                $this->context['resource_owner'] = $model->{$field};
                break;
            }
        }
        
        // 提取常用属性
        $attributes = [];
        foreach (['status', 'state', 'visibility', 'published', 'is_active'] as $field) {
            if (isset($model->{$field})) {
                $attributes[$field] = $model->{$field};
            }
        }
        
        if (!empty($attributes)) {
            $this->withAttributes($attributes);
        }
        
        return $this;
    }

    /**
     * 设置额外的上下文
     *
     * @param array $context
     * @return static
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * 检查是否可以执行指定操作
     *
     * @param string $action
     * @param string $resource
     * @return bool
     */
    public function can(string $action, string $resource): bool
    {
        if (!$this->subject) {
            return false;
        }
        
        // 如果资源包含 ID，自动设置
        if (preg_match('/^(.+)\.(\d+)$/', $resource, $matches)) {
            $resource = $matches[1] . '.*';
            $this->context['resource_id'] = $matches[2];
        }
        
        return $this->permission->check($this->subject, $action, $resource, $this->context);
    }

    /**
     * 检查是否不能执行指定操作
     *
     * @param string $action
     * @param string $resource
     * @return bool
     */
    public function cannot(string $action, string $resource): bool
    {
        return !$this->can($action, $resource);
    }

    /**
     * 检查是否可以执行任一操作
     *
     * @param array $actions
     * @param string $resource
     * @return bool
     */
    public function canAny(array $actions, string $resource): bool
    {
        foreach ($actions as $action) {
            if ($this->can($action, $resource)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查是否可以执行所有操作
     *
     * @param array $actions
     * @param string $resource
     * @return bool
     */
    public function canAll(array $actions, string $resource): bool
    {
        foreach ($actions as $action) {
            if (!$this->can($action, $resource)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 创建新实例
     *
     * @param PermissionSubject|null $subject
     * @return static
     */
    public static function make(?PermissionSubject $subject = null): static
    {
        return new static($subject);
    }

    /**
     * 为当前用户创建检查器
     *
     * @param string|null $guard
     * @return static
     */
    public static function forUser(?string $guard = null): static
    {
        return (new static())->forCurrentUser($guard);
    }

    /**
     * 清理上下文
     *
     * @return static
     */
    public function fresh(): static
    {
        $this->context = [];
        return $this;
    }
}