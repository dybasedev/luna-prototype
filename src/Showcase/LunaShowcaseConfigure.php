<?php

namespace Dybasedev\LunaPrototype\Showcase;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTableRegistry;
use Dybasedev\LunaPrototype\Showcase\RemoteSchema\RemoteSchemaRegistry;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationConfig;
use Illuminate\Contracts\Container\Container;

/**
 * Showcase 组件配置类
 * 
 * 用于配置和注册 DataTable 等 UI 组件
 */
class LunaShowcaseConfigure extends LunaModuleConfigure
{
    /**
     * DataTable 注册器
     * 
     * @var DataTableRegistry|null
     */
    protected(set) ?DataTableRegistry $dataTableRegistry = null;

    /**
     * RemoteSchema 注册器
     * 
     * @var RemoteSchemaRegistry|null
     */
    protected(set) ?RemoteSchemaRegistry $remoteSchemaRegistry = null;

    /**
     * 适配器映射
     * 
     * @var array<string, class-string>
     */
    protected(set) array $adapters = [
        'ant-design-pro' => Adapters\AntDesignProAdapter::class,
    ];

    /**
     * 默认适配器
     * 
     * @var string
     */
    protected(set) string $defaultAdapter = 'ant-design-pro';

    /**
     * Permission 集成配置
     * 
     * @var PermissionIntegrationConfig|null
     */
    protected(set) ?PermissionIntegrationConfig $permissionIntegration = null;

    /**
     * 获取模块名称
     * 
     * @return string
     */
    public function name(): string
    {
        return 'luna.showcase';
    }

    /**
     * 获取服务提供者类名
     * 
     * @return string|null
     */
    public function serviceProvider(): ?string
    {
        return null; // TODO: 如果有专门的 ServiceProvider，这里应该返回类名
    }

    /**
     * 注册服务到容器
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.showcase', function ($app) {
            return new LunaShowcase(
                $app->make(LunaShowcaseConfigure::class)
            );
        });

        $container->alias('luna.showcase', LunaShowcase::class);
    }

    /**
     * 启动配置
     * 
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // 初始化 DataTableRegistry
        if ($this->dataTableRegistry === null) {
            $this->dataTableRegistry = new DataTableRegistry();
        }
        
        // 初始化 RemoteSchemaRegistry
        if ($this->remoteSchemaRegistry === null) {
            $this->remoteSchemaRegistry = new RemoteSchemaRegistry();
        }
        
        // 执行构建逻辑
        $this->dataTableRegistry->build();
        $this->remoteSchemaRegistry->build();
    }

    /**
     * 注册 DataTable
     * 
     * @param string $key DataTable 唯一标识
     * @param class-string|callable $dataTable DataTable 类名或工厂函数
     * @param array $meta 元数据
     * @return $this
     */
    public function registerDataTable(string $key, string|callable $dataTable, array $meta = []): static
    {
        $this->getDataTableRegistry()->register($key, $dataTable, $meta);
        return $this;
    }

    /**
     * 批量注册 DataTable
     * 
     * @param array<string, class-string|array> $dataTables
     * @return $this
     */
    public function registerDataTables(array $dataTables): static
    {
        foreach ($dataTables as $key => $config) {
            if (is_string($config)) {
                $this->registerDataTable($key, $config);
            } elseif (is_array($config) && isset($config['class'])) {
                $meta = $config;
                unset($meta['class']);
                $this->registerDataTable($key, $config['class'], $meta);
            }
        }
        return $this;
    }

    /**
     * 从目录扫描并注册 DataTable
     * 
     * @param string $directory 目录路径
     * @param string $namespace 命名空间
     * @param array $options 选项
     * @return $this
     */
    public function registerDataTablesFromDirectory(string $directory, string $namespace, array $options = []): static
    {
        $this->getDataTableRegistry()->registerFromDirectory($directory, $namespace, $options);
        return $this;
    }

    /**
     * 注册适配器
     * 
     * @param string $name 适配器名称
     * @param class-string $adapter 适配器类名
     * @return $this
     */
    public function registerAdapter(string $name, string $adapter): static
    {
        $this->adapters[$name] = $adapter;
        return $this;
    }

    /**
     * 设置默认适配器
     * 
     * @param string $name
     * @return $this
     */
    public function setDefaultAdapter(string $name): static
    {
        if (!isset($this->adapters[$name])) {
            throw new \InvalidArgumentException("Adapter '{$name}' not found");
        }
        $this->defaultAdapter = $name;
        return $this;
    }

    /**
     * 获取 DataTable 注册器
     * 
     * @return DataTableRegistry
     */
    public function getDataTableRegistry(): DataTableRegistry
    {
        if ($this->dataTableRegistry === null) {
            $this->dataTableRegistry = new DataTableRegistry();
        }
        return $this->dataTableRegistry;
    }

    /**
     * 获取适配器
     * 
     * @param string|null $name
     * @return Adapter
     */
    public function getAdapter(?string $name = null): Adapter
    {
        $name = $name ?? $this->defaultAdapter;
        
        if (!isset($this->adapters[$name])) {
            throw new \InvalidArgumentException("Adapter '{$name}' not found");
        }
        
        $adapterClass = $this->adapters[$name];
        return new $adapterClass();
    }

    /**
     * 注册 RemoteSchema
     * 
     * @param string $key RemoteSchema 唯一标识
     * @param class-string|callable $schema RemoteSchema 类名或工厂函数
     * @param array $meta 元数据
     * @return $this
     */
    public function registerRemoteSchema(string $key, string|callable $schema, array $meta = []): static
    {
        $this->getRemoteSchemaRegistry()->register($key, $schema, $meta);
        return $this;
    }

    /**
     * 批量注册 RemoteSchema
     * 
     * @param array<string, class-string|array> $schemas
     * @return $this
     */
    public function registerRemoteSchemas(array $schemas): static
    {
        foreach ($schemas as $key => $config) {
            if (is_string($config)) {
                $this->registerRemoteSchema($key, $config);
            } elseif (is_array($config) && isset($config['class'])) {
                $meta = $config;
                unset($meta['class']);
                $this->registerRemoteSchema($key, $config['class'], $meta);
            }
        }
        return $this;
    }

    /**
     * 获取 RemoteSchema 注册器
     * 
     * @return RemoteSchemaRegistry
     */
    public function getRemoteSchemaRegistry(): RemoteSchemaRegistry
    {
        if ($this->remoteSchemaRegistry === null) {
            $this->remoteSchemaRegistry = new RemoteSchemaRegistry();
        }
        return $this->remoteSchemaRegistry;
    }

    /**
     * 配置 Permission 集成
     * 
     * @param callable $configurator 接收 PermissionIntegrationBuilder 的闭包
     * @return static
     */
    public function configurePermissionIntegration(callable $configurator): static
    {
        $builder = new PermissionIntegrationBuilder();
        $configurator($builder);
        $this->permissionIntegration = $builder->build();
        return $this;
    }
    
    /**
     * 应用 Permission 集成配置
     * 
     * @param PermissionIntegrationConfig $config
     * @return static
     */
    public function withPermissionIntegration(PermissionIntegrationConfig $config): static
    {
        $this->permissionIntegration = $config;
        return $this;
    }
    
    /**
     * 是否启用了 Permission 集成
     */
    public bool $isPermissionIntegrationEnabled {
        get => $this->permissionIntegration?->enabled ?? false;
    }
    
    /**
     * 获取权限集成配置
     */
    public ?PermissionIntegrationConfig $permissionConfig {
        get => $this->permissionIntegration;
    }

}