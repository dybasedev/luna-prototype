<?php

namespace Dybasedev\LunaPrototype\Foundation;

/**
 * 手动备份对象提供者
 * 
 * 允许手动注册可备份对象，提供更灵活的配置方式。
 * 
 * 使用场景：
 * - 精确控制要备份的对象
 * - 排除某些不需要备份的对象
 * - 为第三方类添加备份支持
 * 
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class BackupableManualProvider implements BackupableProvider
{
    /**
     * 已注册的可备份对象列表
     * 
     * @var array<class-string<Backupable>>
     */
    protected array $backupables = [];

    /**
     * 注册一个可备份对象
     * 
     * @param string $className 实现了 Backupable 接口的类名
     * @return static
     */
    public function register(string $className): static
    {
        if (!in_array($className, $this->backupables)) {
            $this->backupables[] = $className;
        }
        return $this;
    }

    /**
     * 批量注册可备份对象
     * 
     * @param array<class-string<Backupable>> $classNames 类名数组
     * @return static
     */
    public function registerMany(array $classNames): static
    {
        foreach ($classNames as $className) {
            $this->register($className);
        }
        return $this;
    }

    /**
     * 移除一个可备份对象
     * 
     * @param string $className 要移除的类名
     * @return static
     */
    public function remove(string $className): static
    {
        $this->backupables = array_filter(
            $this->backupables,
            fn($class) => $class !== $className
        );
        return $this;
    }

    /**
     * 清空所有已注册的可备份对象
     * 
     * @return static
     */
    public function clear(): static
    {
        $this->backupables = [];
        return $this;
    }

    /**
     * 获取所有可备份对象
     * 
     * @return array<class-string<Backupable>>
     */
    public function backupableObjects(): array
    {
        return array_unique($this->backupables);
    }

    /**
     * 创建手动提供者实例
     * 
     * @param array<class-string<Backupable>> $backupables 初始的可备份对象列表
     * @return static
     */
    public static function create(array $backupables = []): static
    {
        $provider = new static();
        $provider->registerMany($backupables);
        return $provider;
    }
}