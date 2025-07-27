<?php

namespace Dybasedev\LunaPrototype\Showcase\DataTable;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Showcase\Adapter;
use Illuminate\Http\Request;

/**
 * DataTable 管理器
 * 
 * 负责管理和处理所有 DataTable 相关的操作
 */
class DataTableManager
{
    /**
     * 构造函数
     * 
     * @param DataTableRegistry $registry
     * @param Adapter $adapter
     */
    public function __construct(
        protected DataTableRegistry $registry,
        protected Adapter $adapter
    ) {
    }

    /**
     * 获取 DataTable 注册器
     * 
     * @return DataTableRegistry
     */
    public function registry(): DataTableRegistry
    {
        return $this->registry;
    }

    /**
     * 获取 DataTable 实例
     * 
     * @param string $key
     * @return DataTableInterface
     */
    public function get(string $key): DataTableInterface
    {
        return $this->registry->get($key);
    }

    /**
     * 检查 DataTable 是否存在
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    /**
     * 获取所有 DataTable
     * 
     * @param string|null $group
     * @return array
     */
    public function all(?string $group = null): array
    {
        return $this->registry->all($group);
    }

    /**
     * 获取所有分组
     * 
     * @return array
     */
    public function groups(): array
    {
        return $this->registry->groups();
    }

    /**
     * 处理 DataTable 请求
     * 
     * @param string $key DataTable 键
     * @param string $action 动作
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function handleRequest(string $key, string $action, Request $request): mixed
    {
        $dataTable = $this->get($key);

        // 检查权限
        if (!$dataTable->authorized()) {
            throw LunaException::create('Unauthorized')
                ->withDisplayMessage('无权限访问')
                ->withData(['dataTable' => $key]);
        }

        // 根据动作执行相应操作
        return match ($action) {
            'list' => $this->handleList($dataTable, $request),
            'meta' => $this->handleMeta($dataTable, $request),
            'find', 'show' => $this->handleFind($dataTable, $request),
            'create', 'store' => $this->handleCreate($dataTable, $request),
            'update' => $this->handleUpdate($dataTable, $request),
            'delete', 'destroy' => $this->handleDelete($dataTable, $request),
            'batch-delete' => $this->handleBatchDelete($dataTable, $request),
            'export' => $this->handleExport($dataTable, $request),
            default => throw LunaException::create("Unknown action '{$action}'")
                ->withDisplayMessage('未知操作'),
        };
    }

    /**
     * 处理列表请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function handleList(DataTableInterface $dataTable, Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $dataTable->list($request);
    }

    /**
     * 处理元数据请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return array
     */
    protected function handleMeta(DataTableInterface $dataTable, Request $request): array
    {
        $meta = $dataTable->meta($request);

        // 转换列配置为前端格式
        if (isset($meta['columns'])) {
            $meta['columns'] = array_map(
                fn($column) => $this->adapter->column($column),
                $meta['columns']
            );
        }

        // 转换筛选器配置为前端格式
        if (isset($meta['filters'])) {
            $meta['filters'] = array_map(
                fn($filter) => $this->adapter->field($filter),
                $meta['filters']
            );
        }

        return $meta;
    }

    /**
     * 处理查找请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return mixed
     */
    protected function handleFind(DataTableInterface $dataTable, Request $request): mixed
    {
        $result = $dataTable->find($request);

        if (is_null($result)) {
            throw LunaException::create('Record not found')
                ->withDisplayMessage('记录不存在');
        }

        return $result;
    }

    /**
     * 处理创建请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return mixed
     */
    protected function handleCreate(DataTableInterface $dataTable, Request $request): mixed
    {
        return $dataTable->create($request);
    }

    /**
     * 处理更新请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return mixed
     */
    protected function handleUpdate(DataTableInterface $dataTable, Request $request): mixed
    {
        return $dataTable->update($request);
    }

    /**
     * 处理删除请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return mixed
     */
    protected function handleDelete(DataTableInterface $dataTable, Request $request): mixed
    {
        return $dataTable->delete($request);
    }

    /**
     * 处理批量删除请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return int
     */
    protected function handleBatchDelete(DataTableInterface $dataTable, Request $request): int
    {
        return $dataTable->batchDelete($request);
    }

    /**
     * 处理导出请求
     * 
     * @param DataTableInterface $dataTable
     * @param Request $request
     * @return mixed
     */
    protected function handleExport(DataTableInterface $dataTable, Request $request): mixed
    {
        return $dataTable->export($request);
    }

}