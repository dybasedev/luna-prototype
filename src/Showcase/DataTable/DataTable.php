<?php

namespace Dybasedev\LunaPrototype\Showcase\DataTable;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 数据表格抽象类
 * 
 * 提供标准化的后台数据面板实现，包括列表展示、查询、CRUD操作等功能
 */
abstract class DataTable implements DataTableInterface
{
    /**
     * 定义表格列配置
     * 
     * @param Request $request
     * @return array
     */
    abstract public function columns(Request $request): array;

    /**
     * 构建查询构造器
     * 
     * @param Request $request
     * @return Builder
     */
    abstract public function query(Request $request): Builder;

    /**
     * 权限验证
     * 
     * @return bool
     */
    public function authorized(): bool
    {
        return true;
    }

    /**
     * 获取分页列表数据
     *
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $perPage = $request->input('pageSize', 20);
        $page = $request->input('current', 1);
        
        // 获取查询构造器，业务开发者应该在 query() 方法中完成所有查询逻辑
        $query = $this->query($request);
        
        // 执行分页查询
        $result = $query->paginate(
            perPage: $perPage,
            page: $page
        );
        
        // 转换数据
        $result->setCollection(
            $result->getCollection()->map(fn($item) => $this->mapListRecord($item, $request))
        );

        return $result;
    }

    /**
     * 获取可搜索的列
     * 
     * @param Request $request
     * @return array
     */
    protected function getSearchableColumns(Request $request): array
    {
        $columns = $this->columns($request);
        $searchable = [];
        
        foreach ($columns as $column) {
            if ($column instanceof \Dybasedev\LunaPrototype\Showcase\Structures\Column) {
                if ($column->searchable) {
                    $searchable[] = $column->name;
                }
            }
        }
        
        return $searchable;
    }

    /**
     * 转换列表记录
     *
     * @param mixed $record
     * @param Request $request
     * @return mixed
     */
    public function mapListRecord(mixed $record, Request $request): mixed
    {
        return $record;
    }

    /**
     * 转换单条记录
     *
     * @param mixed $record
     * @param Request $request
     * @return mixed
     */
    public function mapRecord(mixed $record, Request $request): mixed
    {
        return $record;
    }

    /**
     * 查找单条记录
     * 
     * @param Request $request
     * @return mixed
     */
    public function find(Request $request): mixed
    {
        $id = $request->input('id');
        
        if (!$id) {
            throw LunaException::create('Missing record ID')
                ->withDisplayMessage('缺少记录ID');
        }
        
        $result = $this->query($request)->find($id);

        if (is_null($result)) {
            return null;
        }

        return $this->mapRecord($result, $request);
    }

    /**
     * 创建记录
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function create(Request $request): mixed
    {
        throw LunaException::create('Create operation not allowed')
            ->withDisplayMessage('不允许创建');
    }

    /**
     * 更新记录
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function update(Request $request): mixed
    {
        throw LunaException::create('Update operation not allowed')
            ->withDisplayMessage('不允许修改');
    }

    /**
     * 删除记录
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function delete(Request $request): mixed
    {
        throw LunaException::create('Delete operation not allowed')
            ->withDisplayMessage('不允许删除');
    }

    /**
     * 批量删除记录
     * 
     * @param Request $request
     * @return int 删除的记录数
     * @throws LunaException
     */
    public function batchDelete(Request $request): int
    {
        throw LunaException::create('Batch delete operation not allowed')
            ->withDisplayMessage('不允许批量删除');
    }

    /**
     * 导出数据
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function export(Request $request): mixed
    {
        throw LunaException::create('Export operation not allowed')
            ->withDisplayMessage('不允许导出');
    }

    /**
     * 获取表格元数据（用于前端渲染）
     * 
     * @param Request $request
     * @return array
     */
    public function meta(Request $request): array
    {
        return [
            'columns' => $this->columns($request),
            'actions' => $this->getActions($request),
            'batch_actions' => $this->getBatchActions($request),
            'filters' => $this->getFilters($request),
            'permissions' => $this->getPermissions($request),
        ];
    }

    /**
     * 获取行操作配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getActions(Request $request): array
    {
        return [];
    }

    /**
     * 获取批量操作配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getBatchActions(Request $request): array
    {
        return [];
    }

    /**
     * 获取筛选器配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getFilters(Request $request): array
    {
        return [];
    }

    /**
     * 获取权限配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getPermissions(Request $request): array
    {
        return [
            'create' => method_exists($this, 'create'),
            'update' => method_exists($this, 'update'),
            'delete' => method_exists($this, 'delete'),
            'export' => method_exists($this, 'export'),
        ];
    }
}