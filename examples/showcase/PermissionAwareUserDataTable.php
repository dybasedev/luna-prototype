<?php

namespace Examples\Showcase;

use Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta;
use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionAwareDataTable;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 权限感知的用户数据表格示例
 * 
 * 展示如何使用 PermissionAwareDataTable 实现权限控制
 * 
 * 权限检查会在以下操作中自动执行：
 * - list: 检查 read 权限，应用所有者过滤
 * - create: 检查 create 权限
 * - update: 检查 update 权限和资源所有权
 * - delete: 检查 delete 权限和资源所有权
 * - export: 检查 export 权限
 */
#[DataTableMeta(
    title: '用户管理（权限控制）',
    description: '带权限控制的用户管理界面',
    group: 'permission',
    sortOrder: 5
)]
class PermissionAwareUserDataTable extends CrudDataTable
{
    use PermissionAwareDataTable;
    
    /**
     * DataTable 键名（用于权限资源映射）
     */
    protected string $dataTableKey = 'permission_users';
    
    /**
     * 权限资源名称
     * 如果不设置，会根据 configure 中的 resourcePattern 自动生成
     */
    protected ?string $permissionResource = 'admin.users';
    
    /**
     * 启用所有者过滤
     * 只显示当前用户创建的记录（除非有 view_all 权限）
     */
    protected bool $enableOwnerFilter = true;
    
    /**
     * 列权限配置
     * 某些敏感列需要特定权限才能查看
     */
    protected array $columnPermissions = [
        // 简单权限：直接指定权限名称
        'email' => 'view_email',
        
        // 复杂权限：指定action和resource
        'phone' => [
            'action' => 'view_phone',
            'resource' => 'admin.users.sensitive'
        ],
        
        // 余额字段需要特殊权限
        'balance' => [
            'action' => 'view_financial',
            'resource' => 'admin.users.financial'
        ],
        
        // 敏感信息
        'id_number' => 'view_sensitive_info',
        'bank_account' => 'view_sensitive_info',
    ];
    
    /**
     * 获取模型类名
     */
    protected function model(): string
    {
        return \App\Models\User::class;
    }
    
    /**
     * 定义列配置
     * 
     * 注意：使用 defineColumns 而不是 columns
     * 列会根据权限自动过滤
     */
    protected function defineColumns(Request $request): array
    {
        return [
            UI::column('ID', 'id')
                ->sortable(true)
                ->width(80),
                
            UI::column('姓名', 'name')
                ->searchable(true)
                ->sortable(true)
                ->copyable(true),
                
            UI::column('邮箱', 'email')
                ->searchable(true)
                ->copyable(true)
                ->tooltip('需要 view_email 权限'),
                
            UI::column('手机', 'phone')
                ->copyable(true)
                ->tooltip('需要 view_phone 权限'),
                
            UI::column('余额', 'balance')
                ->type('number')
                ->sortable(true)
                ->tooltip('需要 view_financial 权限'),
                
            UI::column('身份证号', 'id_number')
                ->copyable(true)
                ->hidden(true)
                ->tooltip('需要 view_sensitive_info 权限'),
                
            UI::column('银行账号', 'bank_account')
                ->hidden(true)
                ->tooltip('需要 view_sensitive_info 权限'),
                
            UI::column('角色', 'role')
                ->searchable(true),
                
            UI::column('状态', 'status')
                ->type('text'),
                
            UI::column('创建者', 'creator'),
                
            UI::column('创建时间', 'created_at')
                ->type('dateTime')
                ->sortable(true)
                ->width(180),
                
            UI::column('操作', 'actions')
                ->type('text')
                ->width(200)
                ->searchable(false),
        ];
    }
    
    /**
     * 构建基础查询
     * 
     * 注意：使用 buildQuery 而不是 query
     * 所有者过滤会自动应用
     */
    protected function buildQuery(Request $request): Builder
    {
        $query = $this->model()::query()
            ->with(['profile', 'roles', 'creator']);
        
        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // 角色过滤
        if ($role = $request->input('filters.role')) {
            $query->where('role', $role);
        }
        
        // 状态过滤
        if ($status = $request->input('filters.status')) {
            $query->where('status', $status);
        }
        
        // 时间范围
        if ($dateRange = $request->input('filters.dateRange')) {
            if (isset($dateRange[0])) {
                $query->where('created_at', '>=', $dateRange[0]);
            }
            if (isset($dateRange[1])) {
                $query->where('created_at', '<=', $dateRange[1]);
            }
        }
        
        // 排序
        $sorter = $request->input('sorter');
        if ($sorter && isset($sorter['field'])) {
            $order = $sorter['order'] === 'ascend' ? 'asc' : 'desc';
            $query->orderBy($sorter['field'], $order);
        } else {
            $query->latest('id');
        }
        
        return $query;
    }
    
    /**
     * 定义操作按钮
     * 
     * 注意：使用 defineActions 而不是 getActions
     * 按钮会根据权限自动过滤
     */
    protected function defineActions(Request $request): array
    {
        return [
            [
                'key' => 'create',
                'label' => '新建用户',
                'type' => 'primary',
                'icon' => 'plus',
            ],
            [
                'key' => 'import',
                'label' => '导入',
                'type' => 'default',
                'icon' => 'upload',
            ],
            [
                'key' => 'export',
                'label' => '导出',
                'type' => 'default',
                'icon' => 'download',
            ],
        ];
    }
    
    /**
     * 定义批量操作
     * 
     * 注意：使用 defineBatchActions 而不是 getBatchActions
     * 操作会根据权限自动过滤
     */
    protected function defineBatchActions(Request $request): array
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
            [
                'key' => 'export',
                'label' => '导出选中',
                'type' => 'default',
            ],
        ];
    }
    
    /**
     * 转换列表记录
     */
    public function mapListRecord(mixed $record, Request $request): mixed
    {
        $data = [
            'id' => $record->id,
            'name' => $record->name,
            'role' => $record->role,
            'status' => $record->status,
            'creator' => $record->creator ? [
                'id' => $record->creator->id,
                'name' => $record->creator->name,
            ] : null,
            'created_at' => $record->created_at->toDateTimeString(),
        ];
        
        // 根据权限添加敏感字段
        // 这些字段已经通过 columnPermissions 过滤
        // 但我们也可以在这里做额外的处理
        if (isset($record->email)) {
            $data['email'] = $record->email;
        }
        
        if (isset($record->phone)) {
            $data['phone'] = $record->phone;
        }
        
        if (isset($record->balance)) {
            $data['balance'] = $record->balance;
        }
        
        if (isset($record->id_number)) {
            $data['id_number'] = $this->maskIdNumber($record->id_number);
        }
        
        if (isset($record->bank_account)) {
            $data['bank_account'] = $this->maskBankAccount($record->bank_account);
        }
        
        // 构建行操作按钮
        $data['actions'] = $this->buildRowActions($record);
        
        return $data;
    }
    
    /**
     * 构建行操作按钮
     */
    protected function buildRowActions(mixed $record): array
    {
        $actions = [];
        
        // 查看按钮（总是显示）
        $actions[] = [
            'key' => 'view',
            'label' => '查看',
            'type' => 'link',
        ];
        
        // 编辑按钮（需要 update 权限）
        // 会自动根据权限过滤
        $actions[] = [
            'key' => 'edit',
            'label' => '编辑',
            'type' => 'link',
        ];
        
        // 只有创建者或管理员可以删除
        if ($this->canDeleteRecord($record)) {
            $actions[] = [
                'key' => 'delete',
                'label' => '删除',
                'type' => 'link',
                'danger' => true,
                'confirm' => '确定要删除这个用户吗？',
            ];
        }
        
        // 根据状态显示不同操作
        if ($record->status === 'active') {
            $actions[] = [
                'key' => 'deactivate',
                'label' => '禁用',
                'type' => 'link',
            ];
        } else {
            $actions[] = [
                'key' => 'activate',
                'label' => '激活',
                'type' => 'link',
            ];
        }
        
        return $actions;
    }
    
    /**
     * 检查是否可以删除记录
     */
    protected function canDeleteRecord(mixed $record): bool
    {
        // 如果有 delete 权限，会在 defineActions 中自动处理
        // 这里可以添加额外的业务逻辑
        
        // 例如：只有创建者可以删除
        if (method_exists($record, 'isOwnedBy')) {
            $holder = \Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegration::getCurrentHolder();
            return $holder && $record->isOwnedBy($holder);
        }
        
        return false;
    }
    
    /**
     * 遮罩身份证号
     */
    protected function maskIdNumber(string $idNumber): string
    {
        if (strlen($idNumber) >= 18) {
            return substr($idNumber, 0, 6) . '********' . substr($idNumber, -4);
        }
        return $idNumber;
    }
    
    /**
     * 遮罩银行账号
     */
    protected function maskBankAccount(string $account): string
    {
        if (strlen($account) >= 8) {
            return substr($account, 0, 4) . '****' . substr($account, -4);
        }
        return $account;
    }
    
    /**
     * 获取创建验证规则
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
     */
    protected function prepareCreateData(Request $request): array
    {
        $data = $request->only(['name', 'email', 'role']);
        
        if ($request->has('password')) {
            $data['password'] = bcrypt($request->input('password'));
        }
        
        $data['status'] = 'pending';
        
        // 设置创建者（owner）
        if ($holder = \Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegration::getCurrentHolder()) {
            $data['owner_type'] = $holder->getOperatorType();
            $data['owner_id'] = $holder->getOperatorId();
        }
        
        return $data;
    }
    
    /**
     * 获取筛选器配置
     */
    protected function getFilters(Request $request): array
    {
        return [
            UI::field('创建时间', 'dateRange')
                ->type('dateRange'),
                
            UI::field('角色', 'role')
                ->type('select')
                ->properties([
                    'options' => [
                        ['label' => '全部', 'value' => ''],
                        ['label' => '管理员', 'value' => 'admin'],
                        ['label' => '普通用户', 'value' => 'user'],
                        ['label' => '访客', 'value' => 'guest'],
                    ]
                ]),
                
            UI::field('状态', 'status')
                ->type('text')
                ->properties([
                    'options' => [
                        ['label' => '全部', 'value' => ''],
                        ['label' => '正常', 'value' => 'active'],
                        ['label' => '禁用', 'value' => 'inactive'],
                        ['label' => '待激活', 'value' => 'pending'],
                    ]
                ]),
        ];
    }
}