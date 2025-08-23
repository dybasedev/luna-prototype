<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Showcase\DataTable\CrudDataTable;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionAwareDataTable;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegration;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Mockery;

class PermissionAwareDataTableTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * 创建测试用的 DataTable
     */
    protected function createTestDataTable($permissionResource = null, $columnPermissions = [], $enableOwnerFilter = false)
    {
        return new class($permissionResource, $columnPermissions, $enableOwnerFilter) extends CrudDataTable {
            use PermissionAwareDataTable;
            
            private $testPermissionResource;
            private $testColumnPermissions;
            private $testEnableOwnerFilter;
            
            public function __construct($permissionResource, $columnPermissions, $enableOwnerFilter)
            {
                $this->testPermissionResource = $permissionResource;
                $this->testColumnPermissions = $columnPermissions;
                $this->testEnableOwnerFilter = $enableOwnerFilter;
            }
            
            protected function model(): string
            {
                return TestModel::class;
            }
            
            protected function configurePermissions(): void
            {
                $this->permissionResource = $this->testPermissionResource;
                $this->columnPermissions = $this->testColumnPermissions;
                $this->enableOwnerFilter = $this->testEnableOwnerFilter;
            }
            
            protected function buildQuery(Request $request): Builder
            {
                $mockBuilder = Mockery::mock(Builder::class);
                $mockBuilder->shouldReceive('with')->andReturnSelf();
                $mockBuilder->shouldReceive('where')->andReturnSelf();
                $mockBuilder->shouldReceive('orderBy')->andReturnSelf();
                return $mockBuilder;
            }
            
            protected function defineColumns(Request $request): array
            {
                return [
                    UI::column('id')->title('ID'),
                    UI::column('name')->title('Name'),
                    UI::column('email')->title('Email'),
                    UI::column('phone')->title('Phone'),
                    UI::column('balance')->title('Balance'),
                ];
            }
            
            protected function defineActions(Request $request): array
            {
                return [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'edit', 'label' => 'Edit'],
                    ['key' => 'delete', 'label' => 'Delete'],
                    ['key' => 'export', 'label' => 'Export'],
                ];
            }
            
            protected function defineBatchActions(Request $request): array
            {
                return [
                    ['key' => 'delete', 'label' => 'Batch Delete'],
                    ['key' => 'export', 'label' => 'Batch Export'],
                ];
            }
        };
    }
    
    public function test_permission_aware_datatable_initialization()
    {
        $dataTable = $this->createTestDataTable('test.resource', ['email' => 'view_email'], true);
        
        // 触发初始化
        $request = Request::create('/');
        $columns = $dataTable->columns($request);
        
        $this->assertIsArray($columns);
    }
    
    public function test_authorized_check_without_permission()
    {
        $dataTable = $this->createTestDataTable(null);
        
        // 没有设置权限资源时，应该调用父类的 authorized 方法
        $this->assertTrue($dataTable->authorized());
    }
    
    public function test_authorized_check_with_permission()
    {
        // 设置 Permission 集成不可用时，默认返回 true（向后兼容）
        $this->app->instance('luna.permission', null);
        
        $dataTable = $this->createTestDataTable('test.resource');
        
        // Permission 不可用时默认允许访问
        $result = $dataTable->authorized();
        $this->assertTrue($result);
        
        // 当 Permission 可用且返回 true 时
        $mockPermission = Mockery::mock('permission');
        $mockPermission->shouldReceive('can')
            ->with('read', 'test.resource', null)
            ->andReturn(true);
        $this->app->instance('luna.permission', $mockPermission);
        
        // Mock helper function
        if (!function_exists('luna_permission')) {
            function luna_permission() {
                return app('luna.permission');
            }
        }
        
        $result = $dataTable->authorized();
        $this->assertTrue($result);
        
        // 当 Permission 可用但返回 false 时
        $mockPermission2 = Mockery::mock('permission');
        $mockPermission2->shouldReceive('can')
            ->with('read', 'test.resource', null)
            ->andReturn(false);
        $this->app->instance('luna.permission', $mockPermission2);
        
        $result = $dataTable->authorized();
        $this->assertFalse($result);
    }
    
    public function test_columns_filtering_without_permissions()
    {
        $dataTable = $this->createTestDataTable('test.resource');
        $request = Request::create('/');
        
        $columns = $dataTable->columns($request);
        
        // 没有列权限配置时，应该返回所有列
        $this->assertCount(5, $columns);
    }
    
    public function test_columns_filtering_with_permissions()
    {
        // Mock Permission 集成
        $this->app->instance('luna.permission', Mockery::mock(LunaPermissionConfigure::class));
        
        $columnPermissions = [
            'email' => 'view_email',
            'phone' => 'view_phone',
        ];
        
        $dataTable = $this->createTestDataTable('test.resource', $columnPermissions);
        $request = Request::create('/');
        
        // 当 Permission 不可用时，应该返回所有列
        $columns = $dataTable->columns($request);
        $this->assertCount(5, $columns);
    }
    
    public function test_query_with_owner_filter_disabled()
    {
        $dataTable = $this->createTestDataTable('test.resource', [], false);
        $request = Request::create('/');
        
        $query = $dataTable->query($request);
        
        // 禁用所有者过滤时，应该直接返回查询
        $this->assertInstanceOf(Builder::class, $query);
    }
    
    public function test_query_with_owner_filter_enabled()
    {
        // 配置 Showcase 集成
        $config = (new PermissionIntegrationBuilder())
            ->enable()
            ->withResourcePattern('test.{key}')
            ->enableOwnerFilter()
            ->build();
        
        $showcase = Mockery::mock(LunaShowcaseConfigure::class);
        $showcase->shouldReceive('permissionConfig')->andReturn($config);
        $this->app->instance(LunaShowcaseConfigure::class, $showcase);
        
        $dataTable = $this->createTestDataTable('test.resource', [], true);
        $request = Request::create('/');
        
        $query = $dataTable->query($request);
        
        // 启用所有者过滤时，应该应用过滤
        $this->assertInstanceOf(Builder::class, $query);
    }
    
    public function test_get_actions_filtering()
    {
        // Mock Permission
        $permission = Mockery::mock('permission');
        $permission->shouldReceive('can')->with('create', 'test.resource')->andReturn(true);
        $permission->shouldReceive('can')->with('update', 'test.resource')->andReturn(false);
        $permission->shouldReceive('can')->with('delete', 'test.resource')->andReturn(true);
        $permission->shouldReceive('can')->with('export', 'test.resource')->andReturn(true);
        
        $this->app->instance('luna.permission', $permission);
        
        // Mock helper function
        if (!function_exists('luna_permission')) {
            function luna_permission() {
                return app('luna.permission');
            }
        }
        
        $dataTable = $this->createTestDataTable('test.resource');
        $request = Request::create('/');
        
        $reflection = new \ReflectionClass($dataTable);
        $method = $reflection->getMethod('getActions');
        $method->setAccessible(true);
        
        $actions = $method->invoke($dataTable, $request);
        
        // 应该过滤掉 edit (因为 update 权限返回 false)
        $actionKeys = array_column($actions, 'key');
        $this->assertContains('create', $actionKeys);
        $this->assertNotContains('edit', $actionKeys);
        $this->assertContains('delete', $actionKeys);
        $this->assertContains('export', $actionKeys);
    }
    
    public function test_get_batch_actions_filtering()
    {
        // Mock Permission
        $permission = Mockery::mock('permission');
        $permission->shouldReceive('can')->with('delete', 'test.resource')->andReturn(false);
        $permission->shouldReceive('can')->with('export', 'test.resource')->andReturn(true);
        
        $this->app->instance('luna.permission', $permission);
        
        $dataTable = $this->createTestDataTable('test.resource');
        $request = Request::create('/');
        
        $reflection = new \ReflectionClass($dataTable);
        $method = $reflection->getMethod('getBatchActions');
        $method->setAccessible(true);
        
        $actions = $method->invoke($dataTable, $request);
        
        // 应该过滤掉 delete
        $actionKeys = array_column($actions, 'key');
        $this->assertNotContains('delete', $actionKeys);
        $this->assertContains('export', $actionKeys);
    }
    
    public function test_meta_includes_permission_info()
    {
        // Mock Permission
        $permission = Mockery::mock('permission');
        $permission->shouldReceive('can')->with('create', 'test.resource')->andReturn(true);
        $permission->shouldReceive('can')->with('read', 'test.resource')->andReturn(true);
        $permission->shouldReceive('can')->with('update', 'test.resource')->andReturn(false);
        $permission->shouldReceive('can')->with('delete', 'test.resource')->andReturn(false);
        $permission->shouldReceive('can')->with('export', 'test.resource')->andReturn(true);
        
        $this->app->instance('luna.permission', $permission);
        
        $dataTable = $this->createTestDataTable('test.resource');
        $request = Request::create('/');
        
        $meta = $dataTable->meta($request);
        
        // 应该包含权限信息
        $this->assertArrayHasKey('permission', $meta);
        $this->assertTrue($meta['permission']['enabled']);
        $this->assertEquals('test.resource', $meta['permission']['resource']);
        $this->assertTrue($meta['permission']['permissions']['create']);
        $this->assertTrue($meta['permission']['permissions']['read']);
        $this->assertFalse($meta['permission']['permissions']['update']);
        $this->assertFalse($meta['permission']['permissions']['delete']);
        $this->assertTrue($meta['permission']['permissions']['export']);
    }
    
    public function test_permission_resource_auto_generation()
    {
        // 配置 Showcase 集成
        $config = (new PermissionIntegrationBuilder())
            ->enable()
            ->withResourcePattern('admin.{key}')
            ->build();
        
        $showcase = new LunaShowcaseConfigure();
        $showcase->withPermissionIntegration($config);
        $this->app->instance(LunaShowcaseConfigure::class, $showcase);
        
        // 创建带有 dataTableKey 的测试类
        $dataTable = new class extends CrudDataTable {
            use PermissionAwareDataTable;
            
            protected string $dataTableKey = 'users';
            
            protected function model(): string
            {
                return TestModel::class;
            }
            
            protected function buildQuery(Request $request): Builder
            {
                return TestModel::query();
            }
            
            protected function defineColumns(Request $request): array
            {
                return [];
            }
            
            // 公开方法以便测试
            public function getPermissionResource(): ?string
            {
                $this->initializePermissionAwareDataTable();
                return $this->permissionResource;
            }
        };
        
        // 应该自动生成资源名
        $this->assertEquals('admin.users', $dataTable->getPermissionResource());
    }
    
    public function test_resource_mapping_override()
    {
        // 配置 Showcase 集成with 资源映射
        $config = (new PermissionIntegrationBuilder())
            ->enable()
            ->withResourcePattern('admin.{key}')
            ->mapResource('users', 'system.users')
            ->build();
        
        $showcase = new LunaShowcaseConfigure();
        $showcase->withPermissionIntegration($config);
        $this->app->instance(LunaShowcaseConfigure::class, $showcase);
        
        $dataTable = new class extends CrudDataTable {
            use PermissionAwareDataTable;
            
            protected string $dataTableKey = 'users';
            
            protected function model(): string
            {
                return TestModel::class;
            }
            
            protected function buildQuery(Request $request): Builder
            {
                return TestModel::query();
            }
            
            protected function defineColumns(Request $request): array
            {
                return [];
            }
            
            public function getPermissionResource(): ?string
            {
                $this->initializePermissionAwareDataTable();
                return $this->permissionResource;
            }
        };
        
        // 应该使用映射的资源名
        $this->assertEquals('system.users', $dataTable->getPermissionResource());
    }
}

// 测试用的模型类
class TestModel extends Model
{
    protected $table = 'test_models';
    
    public static function query()
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        return $builder;
    }
}