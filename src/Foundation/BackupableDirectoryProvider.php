<?php

namespace Dybasedev\LunaPrototype\Foundation;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * 目录备份对象提供者
 * 
 * 扫描指定目录下的所有 PHP 文件，
 * 自动发现并注册实现了 Backupable 接口的类。
 * 
 * 使用场景：
 * - 自动发现模型目录下的可备份对象
 * - 批量注册某个模块的备份对象
 * - 动态加载插件的备份对象
 * 
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class BackupableDirectoryProvider implements BackupableProvider
{
    /**
     * 命名空间前缀
     * 
     * 用于将文件路径转换为类名时的命名空间前缀
     * 
     * @var string|null
     */
    protected(set) ?string $namespace = null;

    /**
     * 构造函数
     * 
     * @param string $path 要扫描的目录路径
     */
    public function __construct(protected(set) string $path)
    {
    }

    /**
     * 创建目录提供者实例
     * 
     * @param string $path 目录路径
     * @return static
     */
    public static function path(string $path): static
    {
        return new static($path);
    }

    /**
     * 设置命名空间前缀
     * 
     * @param string $namespace 命名空间前缀
     * @return static
     */
    public function withNamespace(string $namespace): static
    {
        $this->namespace = rtrim($namespace, '\\');
        return $this;
    }

    /**
     * 获取所有可备份对象
     * 
     * 扫描目录下的所有 PHP 文件，
     * 检查其中的类是否实现了 Backupable 接口。
     * 
     * @return array<class-string<Backupable>>
     */
    public function backupableObjects(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $backupableObjects = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file);
            if ($className === null) {
                continue;
            }

            try {
                // 确保类已加载
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);
                
                // 检查是否实现了 Backupable 接口
                if ($reflection->implementsInterface(Backupable::class) && 
                    !$reflection->isAbstract() && 
                    !$reflection->isInterface() && 
                    !$reflection->isTrait()) {
                    $backupableObjects[] = $className;
                }
            } catch (\Throwable $e) {
                // 忽略无法反射的类
                continue;
            }
        }

        return array_unique($backupableObjects);
    }

    /**
     * 从文件中获取类名
     * 
     * 通过解析文件内容或使用命名空间约定来获取类名。
     * 
     * @param \SplFileInfo $file 文件信息对象
     * @return string|null 类名或 null
     */
    protected function getClassNameFromFile(\SplFileInfo $file): ?string
    {
        $content = file_get_contents($file->getPathname());
        
        // 提取命名空间
        $namespaceMatch = [];
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }
        $namespace = trim($namespaceMatch[1]);

        // 提取类名
        $classMatch = [];
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }
        $className = $classMatch[1];

        return $namespace . '\\' . $className;
    }
}