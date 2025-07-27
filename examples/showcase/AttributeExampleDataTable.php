<?php

namespace Examples\Showcase;

use Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta;
use Dybasedev\LunaPrototype\Showcase\Attributes\Permission;
use Dybasedev\LunaPrototype\Showcase\Attributes\Route;
use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * PHP 8 属性使用示例
 * 
 * 展示如何使用 PHP 8 属性配置 DataTable
 */
#[DataTableMeta(
    title: '商品管理',
    description: '管理商城商品信息',
    group: 'shop',
    sortOrder: 10
)]
#[Permission(['manage-products', 'view-products'], requireAll: false)]
#[Route(
    prefix: 'products',
    except: ['export'],
    middleware: ['auth:admin', 'verified']
)]
class AttributeExampleDataTable extends CrudDataTable
{
    /**
     * 获取模型类名
     * 
     * @return string
     */
    protected function model(): string
    {
        return \App\Models\Product::class;
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
                
            UI::column('name', '商品名称')
                ->setSearch(true)
                ->setSorter(true)
                ->setCopyable(true),
                
            UI::column('sku', 'SKU')
                ->setSearch(true)
                ->setCopyable(true),
                
            UI::column('price', '价格')
                ->setValueType('money')
                ->setSorter(true)
                ->setWidth(120),
                
            UI::column('stock', '库存')
                ->setValueType('number')
                ->setSorter(true)
                ->setWidth(100)
                ->setProperties([
                    'valueEnum' => [
                        0 => ['text' => '缺货', 'status' => 'error'],
                        1 => ['text' => '低库存', 'status' => 'warning'],
                    ]
                ]),
                
            UI::column('status', '状态')
                ->setValueType('badge')
                ->setValueEnum([
                    'active' => ['text' => '上架', 'status' => 'success'],
                    'inactive' => ['text' => '下架', 'status' => 'default'],
                    'pending' => ['text' => '待审核', 'status' => 'warning'],
                ])
                ->setFilters([
                    ['text' => '上架', 'value' => 'active'],
                    ['text' => '下架', 'value' => 'inactive'],
                    ['text' => '待审核', 'value' => 'pending'],
                ]),
                
            UI::column('created_at', '创建时间')
                ->setValueType('dateTime')
                ->setSorter(true)
                ->setWidth(180),
        ];
    }

    /**
     * 构建查询
     * 
     * @param Request $request
     * @return Builder
     */
    public function query(Request $request): Builder
    {
        $query = $this->model()::query()
            ->with(['category', 'brand', 'images']);
        
        // 使用 QueryHelper
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::searchLike(
            $request, 
            ['name', 'sku', 'description']
        ));
        
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::applyCondition(
            $request, 
            'status', 
            'filters.status'
        ));
        
        $query->when(...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::numberRange(
            $request, 
            'price'
        ));
        
        $query->when(
            ...\Dybasedev\LunaPrototype\Showcase\Helpers\QueryHelper::applySorter(
                $request,
                ['name', 'price', 'stock', 'created_at']
            ),
            fn() => $query->latest()
        );
        
        return $query;
    }

    /**
     * 权限验证
     * 
     * @return bool
     */
    public function authorized(): bool
    {
        $reflection = new \ReflectionClass($this);
        $attributes = $reflection->getAttributes(Permission::class);
        
        if (empty($attributes)) {
            return parent::authorized();
        }
        
        $permission = $attributes[0]->newInstance();
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        $permissions = $permission->getPermissions();
        
        if ($permission->requireAll) {
            foreach ($permissions as $perm) {
                if (!$user->can($perm)) {
                    return false;
                }
            }
            return true;
        }
        
        foreach ($permissions as $perm) {
            if ($user->can($perm)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 创建前权限检查
     * 
     * @param Request $request
     * @return void
     */
    #[Permission('create-products')]
    protected function validateCreate(Request $request): void
    {
        // 方法级别的权限检查
        $this->checkMethodPermission(__METHOD__);
        
        parent::validateCreate($request);
    }

    /**
     * 检查方法级别的权限
     * 
     * @param string $method
     * @return void
     * @throws \Dybasedev\LunaPrototype\Foundation\Exception\LunaException
     */
    protected function checkMethodPermission(string $method): void
    {
        $reflection = new \ReflectionMethod($this, $method);
        $attributes = $reflection->getAttributes(Permission::class);
        
        if (empty($attributes)) {
            return;
        }
        
        $permission = $attributes[0]->newInstance();
        $user = auth()->user();
        
        if (!$user || !$user->can($permission->permissions)) {
            throw \Dybasedev\LunaPrototype\Foundation\Exception\LunaException::create('Permission denied')
                ->withDisplayMessage('没有权限执行此操作');
        }
    }
}