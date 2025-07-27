<?php

namespace Dybasedev\LunaPrototype\Showcase\Attributes;

use Attribute;

/**
 * 路由配置注解
 * 
 * 用于自定义 DataTable 的路由行为
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Route
{
    /**
     * 构造函数
     * 
     * @param string|null $prefix 路由前缀
     * @param array $only 只启用的操作
     * @param array $except 排除的操作
     * @param array $middleware 中间件
     */
    public function __construct(
        public ?string $prefix = null,
        public array $only = [],
        public array $except = [],
        public array $middleware = []
    ) {
    }
    
    /**
     * 检查操作是否启用
     * 
     * @param string $action
     * @return bool
     */
    public function isActionEnabled(string $action): bool
    {
        if (!empty($this->only)) {
            return in_array($action, $this->only);
        }
        
        if (!empty($this->except)) {
            return !in_array($action, $this->except);
        }
        
        return true;
    }
}