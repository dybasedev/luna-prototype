<?php

namespace Dybasedev\LunaPrototype\Permission;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Permission\UserGroupContract;
use Dybasedev\LunaPrototype\Permission\Handlers\PermissionHandler;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 权限模块配置
 */
class LunaPermissionConfigure extends LunaModuleConfigure
{
    /**
     * 权限绑定实例
     */
    protected(set) ?PermissionBinding $binding = null;

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
     * 资源定义
     */
    protected(set) array $resources = [];

    /**
     * 超级管理员检查回调
     */
    protected(set) ?\Closure $superAdminChecker = null;

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
     * 设置权限绑定
     *
     * @param PermissionBinding $binding
     * @return $this
     */
    public function bind(PermissionBinding $binding): static
    {
        $this->binding = $binding;
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
     */
    public function boot(Container $container): void
    {
        // 如果设置了绑定，执行绑定初始化
        if ($this->binding && $container->resolved(PermissionBinding::class)) {
            $this->binding->initialize($container);
        }
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
            foreach ($this->resources as $name => $definition) {
                $registry->register($name, $definition);
            }
        }
        
        return $registry;
    }

    /**
     * 获取权限处理器
     *
     * @return PermissionHandler
     */
    public function getPermissionHandler(): PermissionHandler
    {
        static $handler = null;
        
        if ($handler === null) {
            $handler = new PermissionHandler($this->getResourceRegistry());
            
            // 如果设置了超级管理员检查器，注入到处理器
            if ($this->superAdminChecker) {
                $handler->setSuperAdminChecker($this->superAdminChecker);
            }
        }
        
        return $handler;
    }

    /**
     * 获取用户模型类名
     *
     * @return string|null
     */
    public static function getUserModelClass(): ?string
    {
        try {
            $config = app(LunaPermissionConfigure::class);
            if ($config && $config->binding) {
                return $config->binding->getTargetClass();
            }
        } catch (\Throwable $e) {
            // 忽略错误
        }
        
        return null;
    }
}