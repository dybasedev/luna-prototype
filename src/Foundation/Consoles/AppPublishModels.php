<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;

/**
 * 发布 Luna 模块模型到应用程序
 * 
 * 将注册的 Luna 模块中的模型发布到业务项目的 App\Models 目录下。
 * 发布的模型将继承原始模块模型，并保留字段注释以便 IDE 识别。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Consoles
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class AppPublishModels extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'app:publish-models 
                            {--module=* : 指定要发布的模块名称}
                            {--force : 强制覆盖已存在的文件}
                            {--dry-run : 预览要发布的文件，不实际创建}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '发布 Luna 模块的模型到应用程序';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $this->info('=> 发布 Luna 模块模型到应用程序');

        $modules = $this->option('module');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('预览模式：不会创建任何文件');
        }

        // 获取所有已注册的模块
        $registeredModules = $this->getRegisteredModules();

        if (empty($registeredModules)) {
            $this->error('没有找到已注册的 Luna 模块');
            return 1;
        }

        // 过滤要处理的模块
        $modulesToProcess = empty($modules) 
            ? $registeredModules 
            : array_filter($registeredModules, fn($module) => in_array($module->name(), $modules));

        if (empty($modulesToProcess)) {
            $this->error('没有找到指定的模块');
            return 1;
        }

        $publishedCount = 0;

        foreach ($modulesToProcess as $module) {
            $this->info("\n处理模块: {$module->name()}");
            $models = $this->findModelsInModule($module);

            if (empty($models)) {
                $this->comment("  - 该模块没有找到模型");
                continue;
            }

            foreach ($models as $modelClass) {
                if ($this->publishModel($modelClass, $force, $dryRun)) {
                    $publishedCount++;
                }
            }
        }

        $this->info("\n=> 完成！共发布 {$publishedCount} 个模型");

        return 0;
    }

    /**
     * 获取所有已注册的模块
     *
     * @return array
     */
    private function getRegisteredModules(): array
    {
        $provider = $this->laravel->getProvider(LunaServiceProvider::class);
        
        if (!$provider) {
            return [];
        }

        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('modules');
        $property->setAccessible(true);

        return $property->getValue($provider) ?? [];
    }

    /**
     * 查找模块中的模型
     *
     * @param object $module
     * @return array
     */
    private function findModelsInModule(object $module): array
    {
        $models = [];
        $moduleClass = get_class($module);
        $moduleNamespace = Str::beforeLast($moduleClass, '\\');
        $modelsNamespace = $moduleNamespace . '\\Models';

        // 构建模型目录路径
        $modulePath = (new ReflectionClass($moduleClass))->getFileName();
        $modelsPath = dirname($modulePath) . '/Models';

        if (!is_dir($modelsPath)) {
            return $models;
        }

        // 扫描模型目录
        $files = File::glob($modelsPath . '/*.php');

        foreach ($files as $file) {
            $className = $modelsNamespace . '\\' . basename($file, '.php');
            
            if (class_exists($className) && is_subclass_of($className, 'Illuminate\Database\Eloquent\Model')) {
                $models[] = $className;
            }
        }

        return $models;
    }

    /**
     * 发布单个模型
     *
     * @param string $modelClass
     * @param bool $force
     * @param bool $dryRun
     * @return bool
     */
    private function publishModel(string $modelClass, bool $force, bool $dryRun): bool
    {
        $modelName = class_basename($modelClass);
        $targetPath = app_path("Models/{$modelName}.php");

        $this->comment("  - 发布模型: {$modelName}");

        // 检查目标文件是否存在
        if (!$force && File::exists($targetPath)) {
            $this->warn("    文件已存在，跳过: {$targetPath}");
            return false;
        }

        // 生成模型内容
        $content = $this->generateModelContent($modelClass);

        if ($dryRun) {
            $this->info("    将创建: {$targetPath}");
            return true;
        }

        // 确保目标目录存在
        $targetDir = dirname($targetPath);
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // 写入文件
        File::put($targetPath, $content);
        $this->info("    已创建: {$targetPath}");

        return true;
    }

    /**
     * 生成模型内容
     *
     * @param string $modelClass
     * @return string
     */
    private function generateModelContent(string $modelClass): string
    {
        $modelName = class_basename($modelClass);
        $reflection = new ReflectionClass($modelClass);
        
        // 获取模型的属性注释
        $properties = $this->extractModelProperties($reflection);

        $content = "<?php\n\n";
        $content .= "namespace App\\Models;\n\n";
        $content .= "use {$modelClass} as Base{$modelName};\n\n";
        $content .= "/**\n";
        $content .= " * {$modelName} Model\n";
        $content .= " * \n";
        $content .= " * 继承自 Luna 模块的 {$modelName} 模型\n";
        
        // 添加属性注释
        if (!empty($properties)) {
            $content .= " * \n";
            foreach ($properties as $property) {
                $content .= " * {$property}\n";
            }
        }
        
        $content .= " * \n";
        $content .= " * @package App\\Models\n";
        $content .= " */\n";
        $content .= "class {$modelName} extends Base{$modelName}\n";
        $content .= "{\n";
        $content .= "    //\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * 提取模型的属性注释
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    private function extractModelProperties(ReflectionClass $reflection): array
    {
        $properties = [];
        $docComment = $reflection->getDocComment();

        if ($docComment) {
            // 提取 @property 注释
            preg_match_all('/@property(-read|-write)?\s+([^\s]+)\s+\$?(\w+)(?:\s+(.*))?$/m', $docComment, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $modifier = $match[1] ?? '';
                $type = $match[2];
                $name = $match[3];
                $description = $match[4] ?? '';
                
                $propertyLine = "@property{$modifier} {$type} \${$name}";
                if ($description) {
                    $propertyLine .= " {$description}";
                }
                
                $properties[] = $propertyLine;
            }
        }

        // 获取模型实例属性的 fillable 和 casts
        try {
            $instance = new $reflection->name();
            
            // 添加 fillable 属性的注释
            if (property_exists($instance, 'fillable') && !empty($instance->getFillable())) {
                foreach ($instance->getFillable() as $field) {
                    // 检查是否已有该属性的注释
                    $hasProperty = false;
                    foreach ($properties as $prop) {
                        if (preg_match('/\$' . preg_quote($field) . '\b/', $prop)) {
                            $hasProperty = true;
                            break;
                        }
                    }
                    
                    if (!$hasProperty) {
                        // 从 casts 获取类型
                        $type = 'mixed';
                        if (property_exists($instance, 'casts') && isset($instance->getCasts()[$field])) {
                            $castType = $instance->getCasts()[$field];
                            $type = $this->castTypeToPhpType($castType);
                        }
                        
                        $properties[] = "@property {$type} \${$field}";
                    }
                }
            }
        } catch (\Exception $e) {
            // 忽略实例化错误
        }

        return $properties;
    }

    /**
     * 将 cast 类型转换为 PHP 类型
     *
     * @param string $castType
     * @return string
     */
    private function castTypeToPhpType(string $castType): string
    {
        $typeMap = [
            'integer' => 'int',
            'real' => 'float',
            'float' => 'float',
            'double' => 'float',
            'decimal' => 'float',
            'string' => 'string',
            'boolean' => 'bool',
            'object' => 'object',
            'array' => 'array',
            'json' => 'array',
            'collection' => '\Illuminate\Support\Collection',
            'date' => '\Carbon\Carbon',
            'datetime' => '\Carbon\Carbon',
            'timestamp' => '\Carbon\Carbon',
        ];

        return $typeMap[$castType] ?? 'mixed';
    }
}