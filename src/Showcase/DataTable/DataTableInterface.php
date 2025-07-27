<?php

namespace Dybasedev\LunaPrototype\Showcase\DataTable;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * 数据表格接口
 * 
 * 定义数据表格的标准行为
 */
interface DataTableInterface
{
    /**
     * 获取列配置
     * 
     * @param Request $request
     * @return array
     */
    public function columns(Request $request): array;

    /**
     * 获取列表数据
     * 
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function list(Request $request): LengthAwarePaginator;

    /**
     * 查找单条记录
     * 
     * @param Request $request
     * @return mixed
     */
    public function find(Request $request): mixed;

    /**
     * 创建记录
     * 
     * @param Request $request
     * @return mixed
     */
    public function create(Request $request): mixed;

    /**
     * 更新记录
     * 
     * @param Request $request
     * @return mixed
     */
    public function update(Request $request): mixed;

    /**
     * 删除记录
     * 
     * @param Request $request
     * @return mixed
     */
    public function delete(Request $request): mixed;

    /**
     * 批量删除记录
     * 
     * @param Request $request
     * @return int
     */
    public function batchDelete(Request $request): int;

    /**
     * 导出数据
     * 
     * @param Request $request
     * @return mixed
     */
    public function export(Request $request): mixed;

    /**
     * 权限验证
     * 
     * @return bool
     */
    public function authorized(): bool;

    /**
     * 获取元数据
     * 
     * @param Request $request
     * @return array
     */
    public function meta(Request $request): array;
}