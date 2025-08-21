<?php

namespace Dybasedev\LunaPrototype\Permission;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Permission\UserGroupContract;
use Dybasedev\LunaPrototype\Permission\Handlers\PermissionHandler;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 权限模块配置
 */
class LunaPermissionConfigure extends LunaModuleConfigure
{
    /**
     * 权限绑定实例集合
     *
     * @var PermissionBinding[]
     */
    protected(set) array $bindings = [] {
        get {
            return $this->bindings;
        }
    }

    /**
     * 策略模型类名
     *
     * @var class-string<Models\Policy>
     */
    protected(set) string $policyModel = Models\Policy::class;

    /**
     * 策略版本模型类名
     *
     * @var class-string<Models\PolicyVersion>
     */
    protected(set) string $policyVersionModel = Models\PolicyVersion::class;

    /**
     * 角色模型类名
     *
     * @var class-string<Models\Role>
     */
    protected(set) string $roleModel = Models\Role::class;

    /**
     * 用户组接口实现
     *
     * @var class-string<UserGroupContract>|null
     */
    protected(set) ?string $userGroupContract = null;

    /**
     * 策略分配模型类名
     *
     * @var class-string<Models\PolicyAssignment>
     */
    protected(set) string $policyAssignmentModel = Models\PolicyAssignment::class;

    /**
     * 用户组模型类名
     *
     * @var class-string<Models\UserGroup>
     */
    protected(set) string $userGroupModel = Models\UserGroup::class;

    /**
     * 资源定义
     */
    protected(set) array $resources = [];

    /**
     * 资源提供者
     *
     * @var Support\AttributeResourceProvider|null
     */
    protected(set) ?Support\AttributeResourceProvider $resourceProvider = null;

    /**
     * 超级管理员检查回调
     */
    protected(set) ?\Closure $superAdminChecker = null;

    /**
     * 默认权限处理器类
     *
     * @var class-string<Handlers\BasePermissionHandler>
     */
    protected(set) string $defaultHandlerClass = PermissionHandler::class;

    /**
     * 创建配置实例
     *
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * 添加权限绑定
     *
     * @param PermissionBinding $binding
     * @return $this
     */
    public function bind(PermissionBinding $binding): static
    {
        $bindings = $this->bindings;
        $bindings[] = $binding;
        $this->bindings = $bindings;
        return $this;
    }

    /**
     * 使用自定义策略模型
     *
     * @param string $model
     * @return $this
     */
    public function usePolicyModel(string $model): static
    {
        $this->policyModel = $model;
        return $this;
    }

    /**
     * 使用自定义策略版本模型
     *
     * @param string $model
     * @return $this
     */
    public function usePolicyVersionModel(string $model): static
    {
        $this->policyVersionModel = $model;
        return $this;
    }

    /**
     * 使用自定义角色模型
     *
     * @param string $model
     * @return $this
     */
    public function useRoleModel(string $model): static
    {
        $this->roleModel = $model;
        return $this;
    }

    /**
     * 设置用户组接口实现
     *
     * @param string $contract
     * @return $this
     */
    public function useUserGroupContract(string $contract): static
    {
        $this->userGroupContract = $contract;
        return $this;
    }

    /**
     * 使用自定义策略分配模型
     *
     * @param string $model
     * @return $this
     */
    public function usePolicyAssignmentModel(string $model): static
    {
        $this->policyAssignmentModel = $model;
        return $this;
    }

    /**
     * 使用自定义用户组模型
     *
     * @param string $model
     * @return $this
     */
    public function useUserGroupModel(string $model): static
    {
        $this->userGroupModel = $model;
        return $this;
    }

    /**
     * 注册资源定义
     *
     * @param string $name 资源名称
     * @param mixed $definition 资源定义
     * @return $this
     */
    public function registerResource(string $name, $definition): static
    {
        $this->resources[$name] = $definition;
        return $this;
    }

    /**
     * 使用资源提供者
     *
     * @param Support\AttributeResourceProvider $provider
     * @return $this
     */
    public function useResourceProvider(Support\AttributeResourceProvider $provider): static
    {
        $this->resourceProvider = $provider;
        return $this;
    }

    /**
     * 从目录扫描资源（便捷方法）
     *
     * @param string ...$directories 要扫描的目录
     * @return $this
     */
    public function scanResources(string ...$directories): static
    {
        $this->resourceProvider = Support\AttributeResourceProvider::create($directories);
        return $this;
    }

    /**
     * 从应用目录扫描资源
     *
     * @param string ...$paths 相对于 app 目录的路径
     * @return $this
     */
    public function scanAppResources(string ...$paths): static
    {
        $this->resourceProvider = Support\AttributeResourceProvider::fromApp(...$paths);
        return $this;
    }

    /**
     * 设置超级管理员检查器
     *
     * @param \Closure $checker
     * @return $this
     */
    public function setSuperAdminChecker(\Closure $checker): static
    {
        $this->superAdminChecker = $checker;
        return $this;
    }

    /**
     * 获取模块名称
     *
     * @return string
     */
    public function name(): string
    {
        return 'luna.permission';
    }

    /**
     * 获取服务提供者类名
     *
     * @return string|null
     */
    public function serviceProvider(): ?string
    {
        return LunaPermissionServiceProvider::class;
    }


    /**
     * 构建模块
     *
     * @return static|\Closure
     */
    public function build(): static|\Closure
    {
        return $this;
    }

    /**
     * 注册服务
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        // 注册模块单例
        $container->singleton('luna.permission', function ($app) {
            return new LunaPermission(
                $app->make(LunaPermissionConfigure::class),
                $app->make('cache.store')
            );
        });
        $container->alias('luna.permission', LunaPermission::class);
    }

    /**
     * 启动服务
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // 注册权限处理器组和默认处理器
        $container->make(LunaHandlerConfigure::class)->group('permission', '权限', function ($register) {
            $register->handler($this->defaultHandlerClass, 'permission.default');
        });
    }

    /**
     * 获取资源注册器
     *
     * @return ResourceRegistry
     */
    public function getResourceRegistry(): ResourceRegistry
    {
        static $registry = null;

        if ($registry === null) {
            $registry = new ResourceRegistry();

            // 注册手动定义的资源
            foreach ($this->resources as $name => $definition) {
                $registry->register($name, $definition);
            }

            // 从资源提供者获取资源
            if ($this->resourceProvider) {
                $scannedResources = $this->resourceProvider->getResources();
                foreach ($scannedResources as $resource) {
                    $registry->register($resource['name'], $resource);
                }
            }
        }

        return $registry;
    }

    /**
     * 获取权限处理器
     *
     * @return PermissionHandler
     * @throws BindingResolutionException
     */
    public function getPermissionHandler(): PermissionHandler
    {
        static $handler = null;

        if ($handler === null) {
            // 通过 Handler 系统获取纯处理器实例，可以使用别名
            /** @var PermissionHandler $handler */
            $handler = luna_handler()->getPureHandler('permission.default');

            // 设置资源注册器
            $handler->withResourceRegistry($this->getResourceRegistry());

            // 如果设置了超级管理员检查器，注入到处理器
            if ($this->superAdminChecker) {
                $handler->withSuperAdminChecker($this->superAdminChecker);
            }
        }

        return $handler;
    }

    /**
     * 通过模型类名获取绑定
     *
     * @param string $modelClass
     * @return PermissionBinding|null
     */
    public function getBindingByModel(string $modelClass): ?PermissionBinding
    {
        return array_find($this->bindings, fn($binding) => $binding->getTargetClass() === $modelClass);

    }

    /**
     * 通过标识符获取绑定
     *
     * @param string $identifier
     * @return PermissionBinding|null
     */
    public function getBindingByIdentifier(string $identifier): ?PermissionBinding
    {
        return array_find($this->bindings, fn($binding) => $binding->identifier === $identifier);

    }

    /**
     * 获取第一个用户模型类名（向后兼容）
     *
     * @return string|null
     * @deprecated 使用 getBindings() 获取所有绑定
     */
    public static function getUserModelClass(): ?string
    {
        try {
            $config = app(LunaPermissionConfigure::class);
            if ($config && !empty($config->bindings)) {
                return $config->bindings[0]->getTargetClass();
            }
        } catch (\Throwable $e) {
            // 忽略错误
        }

        return null;
    }
}