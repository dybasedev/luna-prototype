<?php

namespace Dybasedev\LunaPrototype\Showcase\DataTable;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Support\Str;

/**
 * DataTable 注册器
 * 
 * 负责管理和注册 DataTable 实例
 */
class DataTableRegistry
{
    /**
     * 已注册的 DataTable
     * 
     * @var array<string, array{class: class-string|callable, meta: array}>
     */
    protected array $dataTables = [];

    /**
     * DataTable 实例缓存
     * 
     * @var array<string, DataTableInterface>
     */
    protected array $instances = [];

    /**
     * 注册 DataTable
     * 
     * @param string $key 唯一标识
     * @param class-string|callable $dataTable DataTable 类名或工厂函数
     * @param array $meta 元数据
     * @return void
     */
    public function register(string $key, string|callable $dataTable, array $meta = []): void
    {
        if (isset($this->dataTables[$key])) {
            throw LunaException::create("DataTable '{$key}' already registered")
                ->withDisplayMessage("DataTable 已注册");
        }

        // 如果是类名，验证类是否存在并实现了接口
        if (is_string($dataTable)) {
            if (!class_exists($dataTable)) {
                throw LunaException::create("DataTable class '{$dataTable}' not found")
                    ->withDisplayMessage("DataTable 类不存在");
            }

            $reflection = new \ReflectionClass($dataTable);
            if (!$reflection->implementsInterface(DataTableInterface::class) && 
                !$reflection->isSubclassOf(DataTable::class)) {
                throw LunaException::create("Class '{$dataTable}' must implement DataTableInterface or extend DataTable")
                    ->withDisplayMessage("类必须实现 DataTableInterface 接口或继承 DataTable");
            }
        }

        // 如果是类名且没有提供元数据，尝试从属性/注解读取
        $defaultMeta = [
            'title' => $this->generateTitle($key),
            'description' => null,
            'group' => 'default',
            'visible' => true,
            'sortOrder' => 0,
        ];
        
        if (is_string($dataTable) && empty($meta)) {
            $generatedMeta = $this->generateMeta($dataTable, '', []);
            // 移除 className 和 file 字段
            unset($generatedMeta['className'], $generatedMeta['file']);
            $defaultMeta = array_merge($defaultMeta, $generatedMeta);
        }
        
        $this->dataTables[$key] = [
            'class' => $dataTable,
            'meta' => array_merge($defaultMeta, $meta),
        ];
    }

    /**
     * 从目录扫描并注册 DataTable
     * 
     * @param string $directory 目录路径
     * @param string $namespace 命名空间
     * @param array $options 选项
     * @return void
     */
    public function registerFromDirectory(string $directory, string $namespace, array $options = []): void
    {
        $defaults = [
            'suffix' => 'DataTable',     // 文件后缀
            'recursive' => true,          // 是否递归扫描
            'exclude' => [],              // 排除的文件名
            'pattern' => null,            // 文件名匹配模式
            'keyGenerator' => null,       // 自定义键生成器
            'metaGenerator' => null,      // 自定义元数据生成器
        ];

        $options = array_merge($defaults, $options);

        if (!is_dir($directory)) {
            throw LunaException::create("Directory '{$directory}' not found")
                ->withDisplayMessage("目录不存在");
        }

        $files = $this->scanDirectory($directory, $options);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file, $directory, $namespace);
            
            if ($className && $this->isValidDataTable($className)) {
                $key = $this->generateKey($className, $file, $options);
                $meta = $this->generateMeta($className, $file, $options);
                
                try {
                    $this->register($key, $className, $meta);
                } catch (LunaException $e) {
                    // 忽略已注册的 DataTable
                    if (!str_contains($e->getMessage(), 'already registered')) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * 扫描目录获取文件
     * 
     * @param string $directory
     * @param array $options
     * @return array
     */
    protected function scanDirectory(string $directory, array $options): array
    {
        $files = [];
        $suffix = $options['suffix'];
        $pattern = $options['pattern'];
        $exclude = $options['exclude'];

        $iterator = $options['recursive'] 
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory))
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();

            // 检查文件后缀
            if (!str_ends_with($filename, $suffix . '.php')) {
                continue;
            }

            // 检查排除列表
            if (in_array($filename, $exclude)) {
                continue;
            }

            // 检查匹配模式
            if ($pattern && !preg_match($pattern, $filename)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * 从文件获取类名
     * 
     * @param string $file
     * @param string $directory
     * @param string $namespace
     * @return string|null
     */
    protected function getClassNameFromFile(string $file, string $directory, string $namespace): ?string
    {
        $relativePath = str_replace($directory, '', $file);
        $relativePath = ltrim($relativePath, '/\\');
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace('/', '\\', $relativePath);

        return $namespace . '\\' . $relativePath;
    }

    /**
     * 验证类是否是有效的 DataTable
     * 
     * @param string $className
     * @return bool
     */
    protected function isValidDataTable(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);

        // 检查是否是抽象类
        if ($reflection->isAbstract()) {
            return false;
        }

        // 检查是否实现了接口或继承了基类
        return $reflection->implementsInterface(DataTableInterface::class) || 
               $reflection->isSubclassOf(DataTable::class);
    }

    /**
     * 生成 DataTable 键
     * 
     * @param string $className
     * @param string $file
     * @param array $options
     * @return string
     */
    protected function generateKey(string $className, string $file, array $options): string
    {
        if (isset($options['keyGenerator']) && is_callable($options['keyGenerator'])) {
            return call_user_func($options['keyGenerator'], $className, $file);
        }

        // 从类名生成键
        $baseName = class_basename($className);
        $suffix = $options['suffix'];
        
        if (str_ends_with($baseName, $suffix)) {
            $baseName = substr($baseName, 0, -strlen($suffix));
        }

        return Str::snake($baseName);
    }

    /**
     * 生成元数据
     * 
     * @param string $className
     * @param string $file
     * @param array $options
     * @return array
     */
    protected function generateMeta(string $className, string $file, array $options): array
    {
        if (isset($options['metaGenerator']) && is_callable($options['metaGenerator'])) {
            return call_user_func($options['metaGenerator'], $className, $file);
        }

        $reflection = new \ReflectionClass($className);
        
        $meta = [
            'className' => $className,
            'file' => $file,
        ];

        // 使用 PHP 8 Attributes
        $attributes = $reflection->getAttributes(\Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta::class);
        if (!empty($attributes)) {
            $attribute = $attributes[0]->newInstance();
            $meta['title'] = $attribute->title;
            $meta['description'] = $attribute->description;
            $meta['group'] = $attribute->group;
            $meta['sortOrder'] = $attribute->sortOrder;
            $meta['visible'] = $attribute->visible;
        }

        return $meta;
    }

    /**
     * 生成标题
     * 
     * @param string $key
     * @return string
     */
    protected function generateTitle(string $key): string
    {
        return Str::title(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * 获取 DataTable 实例
     * 
     * @param string $key
     * @return DataTableInterface
     * @throws LunaException
     */
    public function get(string $key): DataTableInterface
    {
        if (!isset($this->dataTables[$key])) {
            throw LunaException::create("DataTable '{$key}' not found")
                ->withDisplayMessage("DataTable 不存在");
        }

        if (!isset($this->instances[$key])) {
            $config = $this->dataTables[$key];
            $dataTable = $config['class'];

            if (is_callable($dataTable)) {
                $instance = call_user_func($dataTable);
            } else {
                $instance = app($dataTable);
            }

            if (!$instance instanceof DataTableInterface) {
                throw LunaException::create("DataTable '{$key}' must return an instance of DataTableInterface")
                    ->withDisplayMessage("DataTable 必须返回 DataTableInterface 实例");
            }

            $this->instances[$key] = $instance;
        }

        return $this->instances[$key];
    }

    /**
     * 检查 DataTable 是否存在
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->dataTables[$key]);
    }

    /**
     * 获取所有已注册的 DataTable 键
     * 
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->dataTables);
    }

    /**
     * 获取所有 DataTable 的元数据
     * 
     * @param string|null $group 过滤分组
     * @return array<string, array>
     */
    public function all(?string $group = null): array
    {
        $result = [];

        foreach ($this->dataTables as $key => $config) {
            $meta = $config['meta'];
            
            // 过滤不可见的
            if (!($meta['visible'] ?? true)) {
                continue;
            }

            // 过滤分组
            if ($group !== null && ($meta['group'] ?? 'default') !== $group) {
                continue;
            }

            $result[$key] = array_merge(['key' => $key], $meta);
        }

        // 按 sortOrder 排序
        uasort($result, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $result;
    }

    /**
     * 获取分组列表
     * 
     * @return array<string>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->dataTables as $config) {
            $group = $config['meta']['group'] ?? 'default';
            if (!in_array($group, $groups)) {
                $groups[] = $group;
            }
        }

        sort($groups);
        return $groups;
    }

    /**
     * 构建注册器
     * 
     * @return void
     */
    public function build(): void
    {
        // 清理实例缓存
        $this->instances = [];
    }
}