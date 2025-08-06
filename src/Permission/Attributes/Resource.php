<?php

namespace Dybasedev\LunaPrototype\Permission\Attributes;

use Attribute;

/**
 * 资源标记注解
 * 
 * 用于标记类作为权限系统中的资源，支持定义资源名称、描述和支持的操作
 * 
 * @example
 * ```php
 * #[Resource('user', '用户资源', ['create', 'read', 'update', 'delete'])]
 * class UserController
 * {
 *     // ...
 * }
 * 
 * // 或使用更复杂的定义
 * #[Resource(
 *     name: 'article',
 *     description: '文章资源',
 *     actions: ['create', 'read', 'update', 'delete', 'publish', 'unpublish'],
 *     group: 'content',
 *     sortOrder: 10
 * )]
 * class ArticleController
 * {
 *     // ...
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Resource
{
    /**
     * 支持的操作列表
     * 
     * @var array<string>
     */
    public array $actions;
    
    /**
     * 构造函数
     * 
     * @param string $name 资源名称（唯一标识符）
     * @param string $description 资源描述
     * @param array<string> $actions 支持的操作列表，默认为 CRUD 操作
     * @param string $group 资源分组，用于在管理界面中组织资源
     * @param int $sortOrder 排序顺序，用于在列表中排序
     * @param bool $visible 是否在管理界面中可见
     * @param array $metadata 额外的元数据
     */
    public function __construct(
        public string $name,
        public string $description = '',
        array $actions = ['create', 'read', 'update', 'delete'],
        public string $group = 'default',
        public int $sortOrder = 0,
        public bool $visible = true,
        public array $metadata = []
    ) {
        $this->actions = $actions;
    }
    
    /**
     * 获取资源标识符
     * 
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->name;
    }
    
    /**
     * 检查是否支持指定操作
     * 
     * @param string $action
     * @return bool
     */
    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions, true) || in_array('*', $this->actions, true);
    }
    
    /**
     * 获取所有操作的权限标识符
     * 
     * @return array<string>
     */
    public function getPermissionIdentifiers(): array
    {
        return array_map(
            fn($action) => $this->name . ':' . $action,
            $this->actions
        );
    }
    
    /**
     * 转换为数组格式
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'actions' => $this->actions,
            'group' => $this->group,
            'sortOrder' => $this->sortOrder,
            'visible' => $this->visible,
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * 创建简单资源定义
     * 
     * @param string $name
     * @param string $description
     * @param array<string> $actions
     * @return self
     */
    public static function simple(string $name, string $description = '', array $actions = ['create', 'read', 'update', 'delete']): self
    {
        return new self($name, $description, $actions);
    }
    
    /**
     * 创建只读资源定义
     * 
     * @param string $name
     * @param string $description
     * @return self
     */
    public static function readOnly(string $name, string $description = ''): self
    {
        return new self($name, $description, ['read']);
    }
    
    /**
     * 创建完整权限资源定义（包含所有 CRUD 操作）
     * 
     * @param string $name
     * @param string $description
     * @return self
     */
    public static function full(string $name, string $description = ''): self
    {
        return new self($name, $description, ['create', 'read', 'update', 'delete', 'list', 'export', 'import']);
    }
}