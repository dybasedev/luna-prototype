<?php

namespace Dybasedev\LunaPrototype\Permission\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * 模型资源定义
 * 
 * 为 Eloquent 模型自动生成资源定义
 */
class ModelResource extends ResourceDefinition
{
    /**
     * 模型类名
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected(set) string $modelClass;

    /**
     * 创建模型资源
     *
     * @param string $modelClass
     * @param string|null $name
     * @param string|null $description
     */
    public function __construct(string $modelClass, ?string $name = null, ?string $description = null)
    {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class {$modelClass} must extend " . Model::class);
        }

        $this->modelClass = $modelClass;
        
        // 自动生成资源名称
        if ($name === null) {
            $name = $this->generateResourceName($modelClass);
        }

        parent::__construct($name, $description ?? "Resource for {$modelClass}");

        // 设置默认的 CRUD 操作
        $this->actions = ['create', 'read', 'update', 'delete', 'list'];
    }


    /**
     * 生成资源名称
     *
     * @param string $modelClass
     * @return string
     */
    protected function generateResourceName(string $modelClass): string
    {
        $baseName = class_basename($modelClass);
        return Str::snake(Str::pluralStudly($baseName));
    }

    /**
     * 获取模型实例的资源标识符
     *
     * @param Model $model
     * @param string|null $action
     * @return string
     */
    public function getModelIdentifier(Model $model, ?string $action = null): string
    {
        return $this->getIdentifier($model->getKey(), $action);
    }

    /**
     * 创建标准 CRUD 模型资源
     *
     * @param string $modelClass
     * @return static
     */
    public static function forModel(string $modelClass): static
    {
        return new static($modelClass);
    }

    /**
     * 创建只读模型资源
     *
     * @param string $modelClass
     * @return static
     */
    public static function readOnlyModel(string $modelClass): static
    {
        $resource = new static($modelClass);
        $resource->actions = ['read', 'list'];
        return $resource;
    }
}