<?php

namespace Dybasedev\LunaPrototype\Showcase\RemoteSchema;

use Illuminate\Http\Request;

/**
 * RemoteSchema 基类
 * 
 * 提供表单结构描述的基础实现
 */
abstract class RemoteSchema implements RemoteSchemaInterface
{
    /**
     * 获取表单字段结构
     * 
     * @param Request $request
     * @return array
     */
    abstract public function fields(Request $request): array;
    
    /**
     * 获取表单元数据
     * 
     * @param Request $request
     * @return array
     */
    public function meta(Request $request): array
    {
        return [
            'title' => $this->title(),
            'description' => $this->description(),
        ];
    }
    
    /**
     * 获取表单标题
     * 
     * @return string
     */
    protected function title(): string
    {
        return '';
    }
    
    /**
     * 获取表单描述
     * 
     * @return string
     */
    protected function description(): string
    {
        return '';
    }
}