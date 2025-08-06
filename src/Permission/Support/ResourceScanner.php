<?php

namespace Dybasedev\LunaPrototype\Permission\Support;

use Dybasedev\LunaPrototype\Permission\Attributes\Resource;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;
use Dybasedev\LunaPrototype\Permission\Resources\SimpleResource;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * 资源扫描器
 * 
 * 扫描指定目录中带有 Resource 注解的类，并注册到资源注册表中
 */
class ResourceScanner
{
    /**
     * 构造函数
     * 
     * @param ResourceRegistry $registry
     */
    public function __construct(
        protected ResourceRegistry $registry
    ) {
    }
    
    /**
     * 扫描目录中的资源
     * 
     * @param string $directory
     * @param string $namespace
     * @return array 扫描到的资源列表
     */
    public function scan(string $directory, string $namespace): array
    {
        $resources = [];
        
        if (!is_dir($directory)) {
            return $resources;
        }
        
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');
        
        foreach ($finder as $file) {
            $relativePath = str_replace($directory . '/', '', $file->getRealPath());
            $className = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
            
            if (!class_exists($className)) {
                continue;
            }
            
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Resource::class);
            
            foreach ($attributes as $attribute) {
                $resource = $attribute->newInstance();
                $resources[] = $this->registerResource($resource, $className);
            }
        }
        
        return $resources;
    }
    
    /**
     * 扫描多个目录
     * 
     * @param array $directories 目录和命名空间的映射
     * @return array
     */
    public function scanMultiple(array $directories): array
    {
        $allResources = [];
        
        foreach ($directories as $directory => $namespace) {
            $resources = $this->scan($directory, $namespace);
            $allResources = array_merge($allResources, $resources);
        }
        
        return $allResources;
    }
    
    /**
     * 注册资源
     * 
     * @param Resource $attribute
     * @param string $className
     * @return array
     */
    protected function registerResource(Resource $attribute, string $className): array
    {
        $resource = new SimpleResource($attribute->name, $attribute->description);
        $resource->setActions($attribute->actions);
        
        // 注册到资源注册表
        $this->registry->register($attribute->name, $resource);
        
        return [
            'name' => $attribute->name,
            'description' => $attribute->description,
            'actions' => $attribute->actions,
            'group' => $attribute->group,
            'class' => $className,
            'visible' => $attribute->visible,
            'metadata' => $attribute->metadata,
        ];
    }
    
    /**
     * 获取所有已扫描的资源分组
     * 
     * @param array $resources
     * @return array
     */
    public function getGroupedResources(array $resources): array
    {
        $grouped = [];
        
        foreach ($resources as $resource) {
            $group = $resource['group'] ?? 'default';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $resource;
        }
        
        // 对每个组内的资源按 sortOrder 排序
        foreach ($grouped as $group => &$items) {
            usort($items, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
        }
        
        return $grouped;
    }
}