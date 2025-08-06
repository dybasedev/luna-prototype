<?php

namespace Dybasedev\LunaPrototype\Permission\Handlers;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;

/**
 * 权限处理器基类
 * 
 * 提供权限检查的基础功能，子类可以继承此类实现自定义的权限处理逻辑。
 * 不提供构造函数，避免业务端需要调用 parent::__construct。
 * 
 * @package Dybasedev\LunaPrototype\Permission\Handlers
 */
abstract class BasePermissionHandler extends BaseHandler
{
    /**
     * 资源注册器
     * 
     * 延迟初始化，通过 getResourceRegistry() 方法获取
     *
     * @var ResourceRegistry|null
     */
    protected ?ResourceRegistry $resourceRegistry = null;

    /**
     * 策略缓存
     *
     * @var array
     */
    protected array $policyCache = [];

    /**
     * 超级管理员检查回调
     *
     * @var \Closure|null
     */
    protected ?\Closure $superAdminChecker = null;

    /**
     * 标记为纯处理器，不需要数据库实体
     *
     * @return bool
     */
    public static function requiresEntity(): bool
    {
        return false;
    }

    /**
     * 获取资源注册器
     * 
     * 延迟初始化资源注册器，子类可以重写此方法提供自定义的资源注册器
     *
     * @return ResourceRegistry
     */
    protected function getResourceRegistry(): ResourceRegistry
    {
        if ($this->resourceRegistry === null) {
            // 尝试从权限配置中获取
            try {
                $configure = app(\Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure::class);
                $this->resourceRegistry = $configure->getResourceRegistry();
            } catch (\Throwable $e) {
                // 如果获取失败，创建一个空的注册器
                $this->resourceRegistry = new ResourceRegistry();
            }
        }

        return $this->resourceRegistry;
    }

    /**
     * 设置资源注册器
     *
     * @param ResourceRegistry $registry
     * @return static
     */
    public function withResourceRegistry(ResourceRegistry $registry): static
    {
        $this->resourceRegistry = $registry;
        return $this;
    }

    /**
     * 设置超级管理员检查器
     *
     * @param \Closure $checker
     * @return static
     */
    public function withSuperAdminChecker(\Closure $checker): static
    {
        $this->superAdminChecker = $checker;
        return $this;
    }

    /**
     * 检查权限
     *
     * @param PermissionSubject $subject 权限主体
     * @param string $action 操作
     * @param string $resource 资源
     * @param array $context 上下文条件
     * @return bool
     */
    abstract public function check(
        PermissionSubject $subject,
        string $action,
        string $resource,
        array $context = []
    ): bool;

    /**
     * 批量检查权限
     *
     * @param PermissionSubject $subject
     * @param array $permissions 格式: [['action' => 'read', 'resource' => 'users'], ...]
     * @param array $context
     * @return array
     */
    public function checkMany(
        PermissionSubject $subject,
        array $permissions,
        array $context = []
    ): array {
        $results = [];

        foreach ($permissions as $permission) {
            $action = $permission['action'] ?? '*';
            $resource = $permission['resource'] ?? '*';
            
            $results[] = [
                'action' => $action,
                'resource' => $resource,
                'allowed' => $this->check($subject, $action, $resource, $context),
            ];
        }

        return $results;
    }

    /**
     * 检查是否可以执行任一操作
     *
     * @param PermissionSubject $subject
     * @param array $actions
     * @param string $resource
     * @param array $context
     * @return bool
     */
    public function checkAny(
        PermissionSubject $subject,
        array $actions,
        string $resource,
        array $context = []
    ): bool {
        foreach ($actions as $action) {
            if ($this->check($subject, $action, $resource, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否可以执行所有操作
     *
     * @param PermissionSubject $subject
     * @param array $actions
     * @param string $resource
     * @param array $context
     * @return bool
     */
    public function checkAll(
        PermissionSubject $subject,
        array $actions,
        string $resource,
        array $context = []
    ): bool {
        foreach ($actions as $action) {
            if (!$this->check($subject, $action, $resource, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 清除策略缓存
     *
     * @param PermissionSubject|null $subject
     * @return void
     */
    public function clearCache(?PermissionSubject $subject = null): void
    {
        if ($subject) {
            unset($this->policyCache[$subject->getSubjectIdentifier()]);
        } else {
            $this->policyCache = [];
        }
    }

    /**
     * 检查是否为超级管理员
     *
     * @param PermissionSubject $subject
     * @return bool
     */
    protected function isSuperAdmin(PermissionSubject $subject): bool
    {
        // 如果设置了自定义检查器，使用它
        if ($this->superAdminChecker) {
            return call_user_func($this->superAdminChecker, $subject);
        }

        // 默认实现：返回 false，子类可以重写
        return false;
    }
}