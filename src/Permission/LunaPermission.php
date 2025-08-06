<?php

namespace Dybasedev\LunaPrototype\Permission;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\UserGroupContract;
use Dybasedev\LunaPrototype\Permission\Handlers\PermissionHandler;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Permission\Models\UserGroup;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Luna 权限管理模块
 * 
 * 提供基于策略的权限管理系统，不同于传统的 RBAC，本模块采用更灵活的策略驱动方式。
 * 权限策略由效果（Effect）、操作（Action/NotAction）、资源（Resource）、
 * 条件（Condition）和授权主体（Principal）等基本元素组成。
 */
class LunaPermission extends LunaModule
{
    /**
     * 缓存前缀
     * 
     * @var string
     */
    protected const string CACHE_PREFIX = 'luna_permission:';

    /**
     * 默认缓存时间（分钟）
     * 
     * @var int
     */
    protected const int DEFAULT_CACHE_TTL = 60;

    /**
     * 权限处理器实例
     */
    protected ?PermissionHandler $permissionHandler = null;

    /**
     * 资源注册器实例
     */
    protected ?ResourceRegistry $resourceRegistry = null;

    /**
     * 创建权限模块实例
     *
     * @param LunaPermissionConfigure $configure
     * @param Cache $cache
     */
    public function __construct(
        protected(set) LunaPermissionConfigure $configure,
        protected Cache $cache
    ) {}

    /**
     * 获取权限处理器
     *
     * @return PermissionHandler
     */
    public function getPermissionHandler(): PermissionHandler
    {
        if ($this->permissionHandler === null) {
            $this->permissionHandler = $this->configure->getPermissionHandler();
        }
        return $this->permissionHandler;
    }

    /**
     * 获取资源注册器
     *
     * @return ResourceRegistry
     */
    public function getResourceRegistry(): ResourceRegistry
    {
        if ($this->resourceRegistry === null) {
            $this->resourceRegistry = $this->configure->getResourceRegistry();
        }
        return $this->resourceRegistry;
    }

    /**
     * 检查权限
     *
     * @param PermissionSubject $subject
     * @param string $action
     * @param string $resource
     * @param array $context
     * @return bool
     */
    public function check(
        PermissionSubject $subject,
        string $action,
        string $resource,
        array $context = []
    ): bool {
        return $this->getPermissionHandler()->check($subject, $action, $resource, $context);
    }

    /**
     * 批量检查权限
     *
     * @param PermissionSubject $subject
     * @param array $permissions
     * @param array $context
     * @return array
     */
    public function checkMany(
        PermissionSubject $subject,
        array $permissions,
        array $context = []
    ): array {
        return $this->getPermissionHandler()->checkMany($subject, $permissions, $context);
    }

    /**
     * 创建策略
     *
     * @param string $name
     * @param array $statement
     * @param string|null $description
     * @return Policy
     * @throws LunaException
     */
    public function createPolicy(string $name, array $statement, ?string $description = null): Policy
    {
        // 检查策略名称是否已存在
        if ($this->getPolicyByName($name)) {
            throw LunaException::create('策略名称已存在')
                ->withDisplayMessage('策略名称 "' . $name . '" 已被使用');
        }

        return DB::transaction(function () use ($name, $statement, $description) {
            // 使用锁确保策略名称唯一性
            $lockKey = self::CACHE_PREFIX . "lock:policy:create:{$name}";
            
            if (method_exists($this->cache, 'lock')) {
                return $this->cache->lock($lockKey, 10)->block(5, function () use ($name, $statement, $description) {
                    return $this->doCreatePolicy($name, $statement, $description);
                });
            }
            
            // 没有锁支持时直接执行
            return $this->doCreatePolicy($name, $statement, $description);
        });
    }

    /**
     * 执行创建策略
     *
     * @param string $name
     * @param array $statement
     * @param string|null $description
     * @return Policy
     * @throws LunaException
     */
    protected function doCreatePolicy(string $name, array $statement, ?string $description): Policy
    {
        // 再次检查策略名称是否存在（锁内检查）
        if ($this->getPolicyByName($name)) {
            throw LunaException::create('策略名称已存在')
                ->withDisplayMessage('策略名称 "' . $name . '" 已被使用');
        }
        
        $policyClass = $this->configure->policyModel;
        
        /** @var Policy $policy */
        $policy = new $policyClass([
            'name' => $name,
            'description' => $description,
        ]);
        $policy->save();
        
        // 创建初始版本
        $policy->createVersion($statement, '初始版本');
        
        // 清理缓存
        $this->clearPolicyCache($name);
        
        return $policy;
    }

    /**
     * 更新策略
     *
     * @param string|Policy $policy
     * @param array $statement
     * @param string|null $comment
     * @return Policy
     * @throws LunaException
     */
    public function updatePolicy(string|Policy $policy, array $statement, ?string $comment = null): Policy
    {
        if (is_string($policy)) {
            $policy = $this->getPolicyByName($policy);
        }

        if (!$policy) {
            throw LunaException::create('策略不存在')
                ->withDisplayMessage('指定的策略不存在');
        }

        return DB::transaction(function () use ($policy, $statement, $comment) {
            $policy->createVersion($statement, $comment);
            
            // 清理缓存
            $this->clearPolicyCache($policy->name);
            $this->getPermissionHandler()->clearCache();

            return $policy->fresh();
        });
    }

    /**
     * 删除策略
     *
     * @param string|Policy $policy
     * @return bool
     * @throws LunaException
     */
    public function deletePolicy(string|Policy $policy): bool
    {
        if (is_string($policy)) {
            $policy = $this->getPolicyByName($policy);
        }

        if (!$policy) {
            throw LunaException::create('策略不存在')
                ->withDisplayMessage('指定的策略不存在');
        }

        return DB::transaction(function () use ($policy) {
            $policyName = $policy->name;
            $policyId = $policy->id;
            
            // 删除相关的策略分配
            PolicyAssignment::query()->where('policy_id', $policyId)->delete();
            
            // 删除策略版本（VersionControl trait 提供的关系）
            $policy->versions()->delete();
            
            // 删除策略
            $result = $policy->delete();
            
            // 清理缓存
            $this->clearPolicyCache($policyName);
            $this->getPermissionHandler()->clearCache();

            return $result;
        });
    }

    /**
     * 获取策略
     *
     * @param string $name
     * @param bool $withoutCache
     * @return Policy|null
     */
    public function getPolicyByName(string $name, bool $withoutCache = false): ?Policy
    {
        if ($withoutCache) {
            $policyClass = $this->configure->policyModel;
            return $policyClass::findByName($name);
        }

        $cacheKey = self::CACHE_PREFIX . 'policy:' . $name;
        
        return $this->cache->remember($cacheKey, self::DEFAULT_CACHE_TTL, function () use ($name) {
            $policyClass = $this->configure->policyModel;
            return $policyClass::findByName($name);
        });
    }

    /**
     * 获取所有策略
     *
     * @param bool $withoutCache
     * @return Collection
     */
    public function getAllPolicies(bool $withoutCache = false): Collection
    {
        if ($withoutCache) {
            $policyClass = $this->configure->policyModel;
            return $policyClass::all();
        }

        $cacheKey = self::CACHE_PREFIX . 'policies:all';
        
        return collect($this->cache->remember($cacheKey, self::DEFAULT_CACHE_TTL, function () {
            $policyClass = $this->configure->policyModel;
            return $policyClass::all()->all();
        }));
    }

    /**
     * 获取所有策略（别名方法）
     *
     * @return Collection
     */
    public function listPolicies(): Collection
    {
        return $this->getAllPolicies();
    }

    /**
     * 创建角色
     *
     * @param string $name
     * @param string $displayName
     * @param string|null $description
     * @param bool $isSystem
     * @param array $metadata
     * @return Role
     * @throws LunaException
     */
    public function createRole(
        string $name,
        string $displayName,
        ?string $description = null,
        bool $isSystem = false,
        array $metadata = []
    ): Role {
        // 检查角色名称是否已存在
        if ($this->getRoleByName($name)) {
            throw LunaException::create('角色名称已存在')
                ->withDisplayMessage('角色名称 "' . $name . '" 已被使用');
        }

        $roleClass = $this->configure->roleModel;
        
        /** @var Role $role */
        $role = new $roleClass([
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description,
            'is_system' => $isSystem,
            'metadata' => $metadata,
        ]);
        $role->save();

        // 清理缓存
        $this->clearRoleCache($name);

        return $role;
    }

    /**
     * 更新角色
     *
     * @param string|Role $role
     * @param array $attributes
     * @return Role
     * @throws LunaException
     */
    public function updateRole(string|Role $role, array $attributes): Role
    {
        if (is_string($role)) {
            $role = $this->getRoleByName($role);
        }

        if (!$role) {
            throw LunaException::create('角色不存在')
                ->withDisplayMessage('指定的角色不存在');
        }

        // 系统角色不允许修改某些属性
        if ($role->is_system && array_key_exists('name', $attributes)) {
            throw LunaException::create('系统角色不允许修改名称')
                ->withDisplayMessage('系统内置角色的名称不能修改');
        }

        $role->fill($attributes);
        $role->save();

        // 清理缓存
        $this->clearRoleCache($role->name);

        return $role;
    }

    /**
     * 删除角色
     *
     * @param string|Role $role
     * @return bool
     * @throws LunaException
     */
    public function deleteRole(string|Role $role): bool
    {
        if (is_string($role)) {
            $role = $this->getRoleByName($role);
        }

        if (!$role) {
            throw LunaException::create('角色不存在')
                ->withDisplayMessage('指定的角色不存在');
        }

        if ($role->is_system) {
            throw LunaException::create('系统角色不允许删除')
                ->withDisplayMessage('系统内置角色不能删除');
        }

        $roleName = $role->name;
        $roleId = $role->id;
        
        // 删除相关的策略分配
        PolicyAssignment::bySubject('role', $roleId)->delete();
        
        // 删除角色
        $result = $role->delete();
        
        // 清理缓存
        $this->clearRoleCache($roleName);
        $this->getPermissionHandler()->clearCache();

        return $result;
    }

    /**
     * 获取角色
     *
     * @param string $name
     * @param bool $withoutCache
     * @return Role|null
     */
    public function getRoleByName(string $name, bool $withoutCache = false): ?Role
    {
        if ($withoutCache) {
            $roleClass = $this->configure->roleModel;
            return $roleClass::findByName($name);
        }

        $cacheKey = self::CACHE_PREFIX . 'role:' . $name;
        
        return $this->cache->remember($cacheKey, self::DEFAULT_CACHE_TTL, function () use ($name) {
            $roleClass = $this->configure->roleModel;
            return $roleClass::findByName($name);
        });
    }

    /**
     * 获取所有角色
     *
     * @param bool $onlySystem
     * @param bool $withoutCache
     * @return Collection
     */
    public function getAllRoles(bool $onlySystem = false, bool $withoutCache = false): Collection
    {
        $cacheKey = self::CACHE_PREFIX . 'roles:' . ($onlySystem ? 'system' : 'all');

        if ($withoutCache) {
            $roleClass = $this->configure->roleModel;
            $query = $roleClass::query();
            if ($onlySystem) {
                $query->where('is_system', true);
            }
            return $query->get();
        }

        return collect($this->cache->remember($cacheKey, self::DEFAULT_CACHE_TTL, function () use ($onlySystem) {
            $roleClass = $this->configure->roleModel;
            $query = $roleClass::query();
            if ($onlySystem) {
                $query->where('is_system', true);
            }
            return $query->get()->all();
        }));
    }

    /**
     * 获取所有角色（别名方法）
     *
     * @return Collection
     */
    public function listRoles(): Collection
    {
        return $this->getAllRoles();
    }

    /**
     * 创建用户组
     *
     * @param string $name
     * @param string|null $description
     * @param array $metadata
     * @return UserGroupContract
     * @throws LunaException
     */
    public function createUserGroup(
        string $name,
        ?string $description = null,
        array $metadata = []
    ): UserGroupContract {
        $groupClass = $this->configure->userGroupContract ?? UserGroup::class;
        
        // 检查名称是否已存在
        if ($groupClass::query()->where('name', $name)->exists()) {
            throw LunaException::create('用户组名称已存在')
                ->withDisplayMessage('用户组名称 "' . $name . '" 已被使用');
        }

        /** @var UserGroupContract $group */
        $group = new $groupClass([
            'name' => $name,
            'description' => $description,
            'metadata' => $metadata,
        ]);
        $group->save();

        // 清理缓存
        $this->clearUserGroupCache($name);

        return $group;
    }

    /**
     * 分配策略
     *
     * @param PermissionSubject $subject
     * @param string|Policy $policy
     * @param array $options
     * @return PolicyAssignment
     * @throws LunaException
     */
    public function assignPolicy(
        PermissionSubject $subject,
        string|Policy $policy,
        array $options = []
    ): PolicyAssignment {
        if (is_string($policy)) {
            $policy = $this->getPolicyByName($policy);
        }

        if (!$policy) {
            throw LunaException::create('策略不存在')
                ->withDisplayMessage('指定的策略不存在');
        }

        // 验证主体是否存在
        $this->validateSubjectExists($subject);

        // 检查是否已分配
        $existing = PolicyAssignment::bySubject(
            $subject->getSubjectType(),
            $subject->getSubjectId()
        )->where('policy_id', $policy->id)->first();

        if ($existing) {
            if (!$existing->isExpired()) {
                throw LunaException::create('策略已分配')
                    ->withDisplayMessage('该策略已经分配给此主体');
            }
            // 如果已过期，删除旧的分配
            $existing->delete();
        }

        $assignment = PolicyAssignment::assign($policy, $subject, $options);
        
        // 清理权限处理器缓存
        $this->getPermissionHandler()->clearCache($subject);

        return $assignment;
    }

    /**
     * 撤销策略
     *
     * @param PermissionSubject $subject
     * @param string|Policy $policy
     * @return int
     */
    public function revokePolicy(PermissionSubject $subject, string|Policy $policy): int
    {
        if (is_string($policy)) {
            $policy = $this->getPolicyByName($policy);
        }

        if (!$policy) {
            return 0;
        }

        $result = PolicyAssignment::bySubject(
            $subject->getSubjectType(),
            $subject->getSubjectId()
        )->where('policy_id', $policy->id)->delete();

        if ($result > 0) {
            // 清理权限处理器缓存
            $this->getPermissionHandler()->clearCache($subject);
        }

        return $result;
    }

    /**
     * 获取主体的所有策略分配
     *
     * @param PermissionSubject $subject
     * @param bool $includeExpired
     * @return Collection
     */
    public function getSubjectPolicies(PermissionSubject $subject, bool $includeExpired = false): Collection
    {
        $query = PolicyAssignment::bySubject(
            $subject->getSubjectType(),
            $subject->getSubjectId()
        )->with('policy');

        if (!$includeExpired) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * 查询策略分配
     *
     * @param string|null $subjectType
     * @param string|null $policyName
     * @return Builder
     */
    public function queryPolicyAssignments(?string $subjectType = null, ?string $policyName = null): Builder
    {
        $query = PolicyAssignment::query()->with(['policy']);

        if ($subjectType) {
            $query->where('subject_type', $subjectType);
        }

        if ($policyName) {
            $query->whereHas('policy', function ($q) use ($policyName) {
                $q->where('name', $policyName);
            });
        }

        return $query;
    }

    /**
     * 注册资源
     *
     * @param string $name
     * @param mixed $definition
     * @return void
     */
    public function registerResource(string $name, mixed $definition): void
    {
        $this->getResourceRegistry()->register($name, $definition);
    }

    /**
     * 批量注册资源
     *
     * @param array $resources
     * @return void
     */
    public function registerResources(array $resources): void
    {
        $this->getResourceRegistry()->registerMany($resources);
    }

    /**
     * 清理策略缓存
     *
     * @param string $policyName
     * @return void
     */
    protected function clearPolicyCache(string $policyName): void
    {
        $this->cache->forget(self::CACHE_PREFIX . 'policy:' . $policyName);
        $this->cache->forget(self::CACHE_PREFIX . 'policies:all');
    }

    /**
     * 验证主体是否存在
     *
     * @param PermissionSubject $subject
     * @return void
     * @throws LunaException
     */
    protected function validateSubjectExists(PermissionSubject $subject): void
    {
        $subjectType = $subject->getSubjectType();
        $subjectId = $subject->getSubjectId();
        
        // 检查主体是否存在于数据库
        $exists = match ($subjectType) {
            'role' => Role::query()->where('id', $subjectId)->exists(),
            'group' => $this->configure->userGroupContract 
                ? app($this->configure->userGroupContract)::query()->where('id', $subjectId)->exists()
                : UserGroup::query()->where('id', $subjectId)->exists(),
            'user' => $this->checkUserExists($subjectId),
            default => false,
        };
        
        if (!$exists) {
            throw LunaException::create('权限主体不存在')
                ->withDisplayMessage('指定的' . $this->getSubjectTypeDisplayName($subjectType) . '不存在');
        }
    }

    /**
     * 检查用户是否存在
     *
     * @param string|int $userId
     * @return bool
     */
    protected function checkUserExists(string|int $userId): bool
    {
        // 遍历所有的用户绑定
        foreach ($this->configure->bindings as $binding) {
            $modelClass = $binding->getTargetClass();
            if (class_exists($modelClass)) {
                if (app($modelClass)::query()->where('id', $userId)->exists()) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * 获取主体类型显示名称
     *
     * @param string $type
     * @return string
     */
    protected function getSubjectTypeDisplayName(string $type): string
    {
        return match ($type) {
            'role' => '角色',
            'group' => '用户组',
            'user' => '用户',
            default => '主体',
        };
    }

    /**
     * 清理角色缓存
     *
     * @param string $roleName
     * @return void
     */
    protected function clearRoleCache(string $roleName): void
    {
        $this->cache->forget(self::CACHE_PREFIX . 'role:' . $roleName);
        $this->cache->forget(self::CACHE_PREFIX . 'roles:all');
        $this->cache->forget(self::CACHE_PREFIX . 'roles:system');
    }

    /**
     * 清理用户组缓存
     *
     * @param string $groupName
     * @return void
     */
    protected function clearUserGroupCache(string $groupName): void
    {
        $this->cache->forget(self::CACHE_PREFIX . 'usergroup:' . $groupName);
    }

    /**
     * 获取模块名称
     *
     * @return string
     */
    public static function getModuleName(): string
    {
        return 'luna.permission';
    }

    /**
     * 获取模块显示名称
     *
     * @return string
     */
    public static function getModuleDisplayName(): string
    {
        return 'Luna Permission';
    }

    /**
     * 获取模块描述
     *
     * @return string
     */
    public static function getModuleDescription(): string
    {
        return '提供基于策略的权限管理系统，支持灵活的资源访问控制。';
    }

    /**
     * 获取配置类名
     *
     * @return string
     */
    public static function getConfigureClassName(): string
    {
        return LunaPermissionConfigure::class;
    }

    /**
     * 获取服务提供者类名
     *
     * @return string
     */
    public static function getServiceProviderClassName(): string
    {
        return LunaPermissionServiceProvider::class;
    }

    /**
     * 检查当前认证用户是否有权限
     *
     * @param string $action 操作
     * @param string $resource 资源
     * @param array $context 上下文
     * @return bool
     */
    public function can(string $action, string $resource, array $context = []): bool
    {
        $user = auth()->user();
        
        if (!$user instanceof PermissionSubject) {
            return false;
        }
        
        return $this->check($user, $action, $resource, $context);
    }

    /**
     * 检查当前认证用户是否没有权限
     *
     * @param string $action 操作
     * @param string $resource 资源
     * @param array $context 上下文
     * @return bool
     */
    public function cannot(string $action, string $resource, array $context = []): bool
    {
        return !$this->can($action, $resource, $context);
    }

    /**
     * 检查当前认证用户是否有任一权限
     *
     * @param array $actions 操作列表
     * @param string $resource 资源
     * @param array $context 上下文
     * @return bool
     */
    public function canAny(array $actions, string $resource, array $context = []): bool
    {
        foreach ($actions as $action) {
            if ($this->can($action, $resource, $context)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查当前认证用户是否有所有权限
     *
     * @param array $actions 操作列表
     * @param string $resource 资源
     * @param array $context 上下文
     * @return bool
     */
    public function canAll(array $actions, string $resource, array $context = []): bool
    {
        foreach ($actions as $action) {
            if (!$this->can($action, $resource, $context)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 授权检查，失败时抛出异常
     *
     * @param string $action 操作
     * @param string|Model $resource 资源
     * @param array $context 上下文
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorize(string $action, $resource, array $context = []): void
    {
        $user = auth()->user();
        
        if (!$user instanceof PermissionSubject) {
            abort(403, '用户未实现权限接口');
        }
        
        // 添加当前用户到上下文
        $context['current_user'] = $user->getSubjectId();
        
        // 如果是模型实例，提取资源信息
        if ($resource instanceof Model) {
            $resourceName = strtolower(class_basename($resource));
            $resourceId = $resource->getKey();
            
            // 自动提取资源属性
            $context['resource_id'] = $resourceId;
            
            // 尝试提取所有者信息
            foreach (['user_id', 'owner_id', 'created_by'] as $field) {
                if (isset($resource->{$field})) {
                    $context['resource_owner'] = $resource->{$field};
                    break;
                }
            }
            
            $resource = $resourceName . '.' . $resourceId;
        }
        
        if (!$this->check($user, $action, $resource, $context)) {
            abort(403, '无权执行此操作');
        }
    }

    /**
     * 授权检查任一权限，失败时抛出异常
     *
     * @param array $actions 操作列表
     * @param string|Model $resource 资源
     * @param array $context 上下文
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorizeAny(array $actions, $resource, array $context = []): void
    {
        $user = auth()->user();
        
        if (!$user instanceof PermissionSubject) {
            abort(403, '用户未实现权限接口');
        }
        
        // 添加当前用户到上下文
        $context['current_user'] = $user->getSubjectId();
        
        // 处理模型实例
        if ($resource instanceof Model) {
            $resourceName = strtolower(class_basename($resource));
            $context['resource_id'] = $resource->getKey();
            
            foreach (['user_id', 'owner_id', 'created_by'] as $field) {
                if (isset($resource->{$field})) {
                    $context['resource_owner'] = $resource->{$field};
                    break;
                }
            }
            
            $resource = $resourceName . '.' . $resource->getKey();
        }
        
        $hasPermission = false;
        foreach ($actions as $action) {
            if ($this->check($user, $action, $resource, $context)) {
                $hasPermission = true;
                break;
            }
        }
        
        if (!$hasPermission) {
            abort(403, '无权执行任何请求的操作');
        }
    }

    /**
     * 授权检查所有权限，失败时抛出异常
     *
     * @param array $actions 操作列表
     * @param string|Model $resource 资源
     * @param array $context 上下文
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorizeAll(array $actions, $resource, array $context = []): void
    {
        $user = auth()->user();
        
        if (!$user instanceof PermissionSubject) {
            abort(403, '用户未实现权限接口');
        }
        
        // 添加当前用户到上下文
        $context['current_user'] = $user->getSubjectId();
        
        // 处理模型实例
        if ($resource instanceof Model) {
            $resourceName = strtolower(class_basename($resource));
            $context['resource_id'] = $resource->getKey();
            
            foreach (['user_id', 'owner_id', 'created_by'] as $field) {
                if (isset($resource->{$field})) {
                    $context['resource_owner'] = $resource->{$field};
                    break;
                }
            }
            
            $resource = $resourceName . '.' . $resource->getKey();
        }
        
        foreach ($actions as $action) {
            if (!$this->check($user, $action, $resource, $context)) {
                abort(403, '缺少必要的权限');
            }
        }
    }
}