<?php

namespace Dybasedev\LunaPrototype\Permission\Support;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Permission\Attributes\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * 基于 Attribute 的资源提供者
 * 
 * 扫描指定目录中的类，自动发现带有 Resource 注解的类并注册为权限资源。
 * 支持缓存机制以提高性能。
 */
class AttributeResourceProvider
{
    /**
     * 缓存键前缀
     */
    protected const CACHE_PREFIX = 'luna.permission.resources.';

    /**
     * 默认缓存时间（秒）
     */
    protected const CACHE_TTL = 86400; // 24小时

    /**
     * 扫描的目录列表
     */
    protected array $directories = [];

    /**
     * 缓存键
     */
    protected string $cacheKey;

    /**
     * 是否使用缓存
     */
    protected bool $useCache = true;

    /**
     * 缓存过期时间
     */
    protected int $cacheTtl;

    /**
     * 构造函数
     *
     * @param array $directories 要扫描的目录列表
     * @param string|null $cacheKey 自定义缓存键
     */
    public function __construct(array $directories = [], ?string $cacheKey = null)
    {
        $this->directories = $directories;
        $this->cacheKey = $cacheKey ?? self::CACHE_PREFIX . 'default';
        $this->cacheTtl = self::CACHE_TTL;
    }

    /**
     * 添加扫描目录
     *
     * @param string $directory
     * @return static
     */
    public function addDirectory(string $directory): static
    {
        if (!is_dir($directory)) {
            throw LunaException::create("目录不存在: {$directory}");
        }

        $this->directories[] = $directory;
        return $this;
    }

    /**
     * 设置缓存配置
     *
     * @param bool $useCache
     * @param int|null $ttl
     * @return static
     */
    public function withCache(bool $useCache = true, ?int $ttl = null): static
    {
        $this->useCache = $useCache;
        if ($ttl !== null) {
            $this->cacheTtl = $ttl;
        }
        return $this;
    }

    /**
     * 获取所有资源定义
     *
     * @param bool $forceRefresh 是否强制刷新缓存
     * @return array
     */
    public function getResources(bool $forceRefresh = false): array
    {
        // 如果不使用缓存或强制刷新，直接扫描
        if (!$this->useCache || $forceRefresh) {
            $resources = $this->scanDirectories();
            
            // 如果使用缓存，保存结果
            if ($this->useCache) {
                $this->saveToCache($resources);
            }
            
            return $resources;
        }

        // 尝试从缓存获取
        $cached = Cache::get($this->cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存不存在，扫描并缓存
        $resources = $this->scanDirectories();
        $this->saveToCache($resources);
        
        return $resources;
    }

    /**
     * 清除缓存
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        Cache::forget($this->getTimestampCacheKey());
    }

    /**
     * 检查是否需要刷新缓存
     *
     * @return bool
     */
    public function shouldRefreshCache(): bool
    {
        if (!$this->useCache) {
            return true;
        }

        $cachedTimestamp = Cache::get($this->getTimestampCacheKey());
        if ($cachedTimestamp === null) {
            return true;
        }

        // 检查目录中的文件是否有更新
        foreach ($this->directories as $directory) {
            if ($this->hasDirectoryChanged($directory, $cachedTimestamp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 扫描所有目录
     *
     * @return array
     */
    protected function scanDirectories(): array
    {
        $resources = [];

        foreach ($this->directories as $directory) {
            $resources = array_merge($resources, $this->scanDirectory($directory));
        }

        // 按 sortOrder 排序
        usort($resources, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $resources;
    }

    /**
     * 扫描单个目录
     *
     * @param string $directory
     * @return array
     */
    protected function scanDirectory(string $directory): array
    {
        $resources = [];
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file->getPathname());
            
            if (!$className || !class_exists($className)) {
                continue;
            }

            $resource = $this->extractResourceFromClass($className);
            if ($resource) {
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /**
     * 从类中提取资源定义
     *
     * @param string $className
     * @return array|null
     */
    protected function extractResourceFromClass(string $className): ?array
    {
        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Resource::class);

            if (empty($attributes)) {
                return null;
            }

            /** @var Resource $resource */
            $resource = $attributes[0]->newInstance();
            
            return array_merge($resource->toArray(), [
                'class' => $className,
                'file' => $reflection->getFileName(),
            ]);
        } catch (\Throwable $e) {
            // 忽略无法解析的类
            return null;
        }
    }

    /**
     * 从文件路径获取类名
     *
     * @param string $filepath
     * @return string|null
     */
    protected function getClassNameFromFile(string $filepath): ?string
    {
        $contents = file_get_contents($filepath);
        
        // 提取命名空间
        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
        } else {
            return null;
        }

        // 提取类名
        if (preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
            $className = $classMatch[1];
        } else {
            return null;
        }

        return $namespace . '\\' . $className;
    }

    /**
     * 检查目录是否有变化
     *
     * @param string $directory
     * @param int $timestamp
     * @return bool
     */
    protected function hasDirectoryChanged(string $directory, int $timestamp): bool
    {
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php')->date('>= ' . date('Y-m-d H:i:s', $timestamp));
        
        return $finder->hasResults();
    }

    /**
     * 保存到缓存
     *
     * @param array $resources
     * @return void
     */
    protected function saveToCache(array $resources): void
    {
        Cache::put($this->cacheKey, $resources, $this->cacheTtl);
        Cache::put($this->getTimestampCacheKey(), time(), $this->cacheTtl);
    }

    /**
     * 获取时间戳缓存键
     *
     * @return string
     */
    protected function getTimestampCacheKey(): string
    {
        return $this->cacheKey . '.timestamp';
    }

    /**
     * 创建实例的便捷方法
     *
     * @param array $directories
     * @return static
     */
    public static function create(array $directories = []): static
    {
        return new static($directories);
    }

    /**
     * 从应用目录创建
     *
     * @param string ...$paths 相对于 app 目录的路径
     * @return static
     */
    public static function fromApp(string ...$paths): static
    {
        $directories = array_map(fn($path) => app_path($path), $paths);
        return new static($directories);
    }
}