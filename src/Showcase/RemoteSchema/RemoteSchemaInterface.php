<?php

namespace Dybasedev\LunaPrototype\Showcase\RemoteSchema;

use Illuminate\Http\Request;

/**
 * RemoteSchema 接口
 * 
 * 定义了远程表单结构描述的基本契约
 */
interface RemoteSchemaInterface
{
    /**
     * 获取表单字段结构
     * 
     * @param Request $request
     * @return array
     */
    public function fields(Request $request): array;
    
    /**
     * 获取表单元数据
     * 
     * @param Request $request
     * @return array
     */
    public function meta(Request $request): array;
}