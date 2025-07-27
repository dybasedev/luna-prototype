<?php

namespace Examples\Showcase;

use Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta;
use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 用户数据表格示例
 * 
 * 展示如何使用 DataTable 构建一个完整的用户管理界面
 */
#[DataTableMeta(
    title: '用户管理',
    description: '管理系统用户账号',
    group: 'system',
    sortOrder: 10
)]
class UserDataTable extends CrudDataTable
{
    /**
     * 获取模型类名
     * 
     * @return string
     */
    protected function model(): string
    {
        return \App\Models\User::class;
    }

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
                
            UI::column('name', '姓名')
                ->setSearch(true)
                ->setSorter(true)
                ->setCopyable(true),
                
            UI::column('email', '邮箱')
                ->setSearch(true)
                ->setCopyable(true),
                
            UI::column('role', '角色')
                ->setFilters([
                    ['text' => '管理员', 'value' => 'admin'],
                    ['text' => '普通用户', 'value' => 'user'],
                    ['text' => '访客', 'value' => 'guest'],
                ]),
                
            UI::column('status', '状态')
                ->setValueType('badge')
                ->setValueEnum([
                    'active' => ['text' => '正常', 'status' => 'success'],
                    'inactive' => ['text' => '禁用', 'status' => 'error'],
                    'pending' => ['text' => '待激活', 'status' => 'warning'],
                ]),
                
            UI::column('created_at', '创建时间')
                ->setValueType('dateTime')
                ->setSorter(true)
                ->setWidth(180),
                
            UI::column('actions', '操作')
                ->setValueType('option')
                ->setFixed('right')
                ->setWidth(200),
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
        $query = $this->model()::query()
            ->with(['profile', 'roles']); // 预加载关联
        
        // 使用 QueryHelper 实现搜索和过滤
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::searchLike(
            $request, 
            ['name', 'email'], 
            'search'
        ));
        
        // 角色过滤
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::applyCondition(
            $request, 
            'role', 
            'filters.role'
        ));
        
        // 状态过滤
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::applyCondition(
            $request, 
            'status', 
            'filters.status'
        ));
        
        // 创建时间范围
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::dateBetween(
            $request, 
            'created_at', 
            'filters.dateRange'
        ));
        
        // 排序
        $query->when(
            ...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::applySorter($request),
            fn() => $query->latest('id') // 默认排序
        );
        
        return $query;
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
            'name' => $record->name,
            'email' => $record->email,
            'role' => $record->role,
            'status' => $record->status,
            'created_at' => $record->created_at->toDateTimeString(),
            'actions' => $this->buildActions($record),
        ];
    }

    /**
     * 构建行操作按钮
     * 
     * @param mixed $record
     * @return array
     */
    protected function buildActions(mixed $record): array
    {
        $actions = [];
        
        $actions[] = [
            'key' => 'view',
            'label' => '查看',
            'type' => 'primary',
        ];
        
        if ($record->status !== 'active') {
            $actions[] = [
                'key' => 'activate',
                'label' => '激活',
                'type' => 'success',
            ];
        }
        
        $actions[] = [
            'key' => 'edit',
            'label' => '编辑',
            'type' => 'default',
        ];
        
        $actions[] = [
            'key' => 'delete',
            'label' => '删除',
            'type' => 'danger',
            'confirm' => '确定要删除这个用户吗？',
        ];
        
        return $actions;
    }

    /**
     * 获取创建验证规则
     * 
     * @param Request $request
     * @return array
     */
    protected function createRules(Request $request): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,user,guest',
        ];
    }

    /**
     * 获取更新验证规则
     * 
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    protected function updateRules(Request $request, \Illuminate\Database\Eloquent\Model $model): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $model->id,
            'password' => 'sometimes|required|string|min:8|confirmed',
            'role' => 'sometimes|required|in:admin,user,guest',
        ];
    }

    /**
     * 准备创建数据
     * 
     * @param Request $request
     * @return array
     */
    protected function prepareCreateData(Request $request): array
    {
        $data = $request->only(['name', 'email', 'role']);
        
        if ($request->has('password')) {
            $data['password'] = bcrypt($request->input('password'));
        }
        
        $data['status'] = 'pending';
        
        return $data;
    }

    /**
     * 准备更新数据
     * 
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    protected function prepareUpdateData(Request $request, \Illuminate\Database\Eloquent\Model $model): array
    {
        $data = $request->only(['name', 'email', 'role', 'status']);
        
        if ($request->has('password')) {
            $data['password'] = bcrypt($request->input('password'));
        }
        
        return array_filter($data, fn($value) => !is_null($value));
    }

    /**
     * 创建后钩子
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param Request $request
     * @return void
     */
    protected function afterCreate(\Illuminate\Database\Eloquent\Model $model, Request $request): void
    {
        // 发送欢迎邮件
        // Mail::to($model->email)->send(new WelcomeEmail($model));
        
        // 记录操作日志
        // activity()->performedOn($model)->log('created');
    }

    /**
     * 获取行操作配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getActions(Request $request): array
    {
        return [
            [
                'key' => 'create',
                'label' => '新建用户',
                'type' => 'primary',
                'icon' => 'plus',
            ],
        ];
    }

    /**
     * 获取批量操作配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getBatchActions(Request $request): array
    {
        return [
            [
                'key' => 'delete',
                'label' => '批量删除',
                'type' => 'danger',
                'confirm' => '确定要删除选中的用户吗？',
            ],
            [
                'key' => 'activate',
                'label' => '批量激活',
                'type' => 'success',
            ],
            [
                'key' => 'deactivate',
                'label' => '批量禁用',
                'type' => 'warning',
            ],
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
            UI::field('dateRange', '创建时间')
                ->setType('dateRange'),
                
            UI::field('role', '角色')
                ->setType('select')
                ->setOptions([
                    ['label' => '全部', 'value' => ''],
                    ['label' => '管理员', 'value' => 'admin'],
                    ['label' => '普通用户', 'value' => 'user'],
                    ['label' => '访客', 'value' => 'guest'],
                ]),
                
            UI::field('status', '状态')
                ->setType('radioButton')
                ->setOptions([
                    ['label' => '全部', 'value' => ''],
                    ['label' => '正常', 'value' => 'active'],
                    ['label' => '禁用', 'value' => 'inactive'],
                    ['label' => '待激活', 'value' => 'pending'],
                ]),
        ];
    }
}