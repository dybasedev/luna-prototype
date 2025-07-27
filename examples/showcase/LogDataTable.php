<?php

namespace Examples\Showcase;

use Dybasedev\LunaPrototype\Showcase\DataTable\DataTable;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 日志数据表格示例
 * 
 * 展示如何使用 DataTable 构建一个只读的日志查看界面
 */
class LogDataTable extends DataTable
{
    /**
     * 定义表格列配置
     * 
     * @param Request $request
     * @return array
     */
    public function columns(Request $request): array
    {
        return [
            UI::column('id', 'ID')
                ->setSorter(true)
                ->setWidth(80),
                
            UI::column('level', '级别')
                ->setValueType('badge')
                ->setValueEnum([
                    'debug' => ['text' => 'DEBUG', 'status' => 'default'],
                    'info' => ['text' => 'INFO', 'status' => 'processing'],
                    'warning' => ['text' => 'WARNING', 'status' => 'warning'],
                    'error' => ['text' => 'ERROR', 'status' => 'error'],
                    'critical' => ['text' => 'CRITICAL', 'status' => 'error'],
                ])
                ->setFilters([
                    ['text' => 'DEBUG', 'value' => 'debug'],
                    ['text' => 'INFO', 'value' => 'info'],
                    ['text' => 'WARNING', 'value' => 'warning'],
                    ['text' => 'ERROR', 'value' => 'error'],
                    ['text' => 'CRITICAL', 'value' => 'critical'],
                ])
                ->setWidth(100),
                
            UI::column('category', '分类')
                ->setSearch(true)
                ->setEllipsis(true),
                
            UI::column('message', '消息')
                ->setSearch(true)
                ->setEllipsis(true)
                ->setTooltip(true),
                
            UI::column('user_id', '用户ID')
                ->setSearch(true)
                ->setWidth(100),
                
            UI::column('ip_address', 'IP地址')
                ->setCopyable(true)
                ->setWidth(140),
                
            UI::column('user_agent', '用户代理')
                ->setEllipsis(true)
                ->setTooltip(true)
                ->setHideInTable(true), // 默认隐藏
                
            UI::column('created_at', '时间')
                ->setValueType('dateTime')
                ->setSorter(true)
                ->setWidth(180),
                
            UI::column('actions', '操作')
                ->setValueType('option')
                ->setFixed('right')
                ->setWidth(100),
        ];
    }

    /**
     * 构建查询构造器
     * 
     * @param Request $request
     * @return Builder
     */
    public function query(Request $request): Builder
    {
        // 假设有一个 Log 模型
        return \App\Models\Log::query()
            ->when($request->has('level'), function ($query) use ($request) {
                $query->where('level', $request->input('level'));
            })
            ->when($request->has('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->input('user_id'));
            })
            ->when($request->has('date_range'), function ($query) use ($request) {
                $range = $request->input('date_range');
                if (isset($range[0]) && isset($range[1])) {
                    $query->whereBetween('created_at', [$range[0], $range[1]]);
                }
            })
            ->orderBy('created_at', 'desc'); // 默认按时间倒序
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
        return [
            'id' => $record->id,
            'level' => $record->level,
            'category' => $record->category,
            'message' => $record->message,
            'user_id' => $record->user_id,
            'ip_address' => $record->ip_address,
            'user_agent' => $record->user_agent,
            'created_at' => $record->created_at->toDateTimeString(),
            'actions' => [
                [
                    'key' => 'view',
                    'label' => '查看详情',
                    'type' => 'link',
                ],
            ],
        ];
    }

    /**
     * 转换单条记录（查看详情时）
     * 
     * @param mixed $record
     * @param Request $request
     * @return mixed
     */
    public function mapRecord(mixed $record, Request $request): mixed
    {
        return [
            'id' => $record->id,
            'level' => $record->level,
            'category' => $record->category,
            'message' => $record->message,
            'user_id' => $record->user_id,
            'ip_address' => $record->ip_address,
            'user_agent' => $record->user_agent,
            'context' => $record->context, // 详情页显示上下文数据
            'stack_trace' => $record->stack_trace, // 详情页显示堆栈信息
            'created_at' => $record->created_at->toDateTimeString(),
        ];
    }

    /**
     * 获取筛选器配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getFilters(Request $request): array
    {
        return [
            UI::field('date_range', '时间范围')
                ->setType('dateRange')
                ->setPlaceholder(['开始时间', '结束时间']),
                
            UI::field('level', '日志级别')
                ->setType('select')
                ->setOptions([
                    ['label' => '全部级别', 'value' => ''],
                    ['label' => 'DEBUG', 'value' => 'debug'],
                    ['label' => 'INFO', 'value' => 'info'],
                    ['label' => 'WARNING', 'value' => 'warning'],
                    ['label' => 'ERROR', 'value' => 'error'],
                    ['label' => 'CRITICAL', 'value' => 'critical'],
                ]),
                
            UI::field('user_id', '用户ID')
                ->setType('text')
                ->setPlaceholder('输入用户ID'),
        ];
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
            'create' => false, // 日志不允许创建
            'update' => false, // 日志不允许修改
            'delete' => false, // 日志不允许删除
            'export' => true,  // 允许导出
        ];
    }

    /**
     * 导出数据
     * 
     * @param Request $request
     * @return mixed
     */
    public function export(Request $request): mixed
    {
        // 获取要导出的数据
        $query = $this->applyFilters($this->query($request), $request);
        $data = $query->get();
        
        // 转换数据格式
        $exportData = $data->map(function ($record) {
            return [
                'ID' => $record->id,
                '级别' => $record->level,
                '分类' => $record->category,
                '消息' => $record->message,
                '用户ID' => $record->user_id,
                'IP地址' => $record->ip_address,
                '时间' => $record->created_at->toDateTimeString(),
            ];
        });
        
        // 这里可以返回 CSV、Excel 等格式
        // 示例返回数组，实际使用时可以集成 Laravel Excel 等库
        return [
            'filename' => 'logs_' . date('Y-m-d_H-i-s') . '.csv',
            'data' => $exportData->toArray(),
        ];
    }
}