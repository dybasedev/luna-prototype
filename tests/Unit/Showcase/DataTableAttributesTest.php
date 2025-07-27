<?php

use Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta;
use Dybasedev\LunaPrototype\Showcase\Attributes\Permission;
use Dybasedev\LunaPrototype\Showcase\Attributes\Route;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTable;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTableRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// 测试用的 DataTable 类 - 使用 PHP 8 属性
#[DataTableMeta(
    title: '测试数据表',
    description: '用于测试的数据表',
    group: 'test',
    sortOrder: 100
)]
#[Permission('manage-tests')]
#[Route(prefix: 'tests', except: ['export'])]
class AttributeTestDataTable extends DataTable
{
    public function columns(Request $request): array
    {
        return [];
    }
    
    public function query(Request $request): Builder
    {
        return \Illuminate\Database\Eloquent\Model::query();
    }
}

// 测试用的 DataTable 类 - 不使用任何属性
class LegacyTestDataTable extends DataTable
{
    public function columns(Request $request): array
    {
        return [];
    }
    
    public function query(Request $request): Builder
    {
        return \Illuminate\Database\Eloquent\Model::query();
    }
}

// 测试用的 DataTable 类 - 没有注解
class NoAttributeTestDataTable extends DataTable
{
    public function columns(Request $request): array
    {
        return [];
    }
    
    public function query(Request $request): Builder
    {
        return \Illuminate\Database\Eloquent\Model::query();
    }
}

test('可以读取PHP 8属性元数据', function () {
    $registry = new DataTableRegistry();
    $registry->register('attribute_test', AttributeTestDataTable::class);
    
    $meta = $registry->get('attribute_test');
    $allDataTables = $registry->all();
    
    expect($allDataTables['attribute_test'])->toMatchArray([
        'key' => 'attribute_test',
        'title' => '测试数据表',
        'description' => '用于测试的数据表',
        'group' => 'test',
        'sortOrder' => 100,
        'visible' => true,
    ]);
});

test('没有属性时使用默认元数据', function () {
    $registry = new DataTableRegistry();
    $registry->register('legacy_test', LegacyTestDataTable::class);
    
    $allDataTables = $registry->all();
    
    expect($allDataTables['legacy_test'])->toMatchArray([
        'key' => 'legacy_test',
        'title' => 'Legacy Test',
        'group' => 'default',
        'sortOrder' => 0,
        'visible' => true,
    ]);
});

test('没有注解时使用默认值', function () {
    $registry = new DataTableRegistry();
    $registry->register('no_attribute', NoAttributeTestDataTable::class);
    
    $allDataTables = $registry->all();
    
    expect($allDataTables['no_attribute'])->toMatchArray([
        'key' => 'no_attribute',
        'title' => 'No Attribute', // 从key生成的默认标题
        'group' => 'default',
        'sortOrder' => 0,
        'visible' => true,
    ]);
});

test('权限属性可以正确实例化', function () {
    $reflection = new ReflectionClass(AttributeTestDataTable::class);
    $attributes = $reflection->getAttributes(Permission::class);
    
    expect($attributes)->toHaveCount(1);
    
    $permission = $attributes[0]->newInstance();
    expect($permission->permissions)->toBe('manage-tests');
    expect($permission->guard)->toBe('web');
    expect($permission->requireAll)->toBeFalse();
    expect($permission->getPermissions())->toBe(['manage-tests']);
});

test('权限属性支持多个权限', function () {
    $permission = new Permission(['view-tests', 'edit-tests'], requireAll: true);
    
    expect($permission->getPermissions())->toBe(['view-tests', 'edit-tests']);
    expect($permission->requireAll)->toBeTrue();
});

test('路由属性可以正确实例化', function () {
    $reflection = new ReflectionClass(AttributeTestDataTable::class);
    $attributes = $reflection->getAttributes(Route::class);
    
    expect($attributes)->toHaveCount(1);
    
    $route = $attributes[0]->newInstance();
    expect($route->prefix)->toBe('tests');
    expect($route->except)->toBe(['export']);
    expect($route->only)->toBe([]);
    expect($route->middleware)->toBe([]);
});

test('路由属性可以检查操作是否启用', function () {
    $route1 = new Route(only: ['list', 'create']);
    expect($route1->isActionEnabled('list'))->toBeTrue();
    expect($route1->isActionEnabled('create'))->toBeTrue();
    expect($route1->isActionEnabled('delete'))->toBeFalse();
    
    $route2 = new Route(except: ['delete', 'export']);
    expect($route2->isActionEnabled('list'))->toBeTrue();
    expect($route2->isActionEnabled('create'))->toBeTrue();
    expect($route2->isActionEnabled('delete'))->toBeFalse();
    expect($route2->isActionEnabled('export'))->toBeFalse();
    
    $route3 = new Route();
    expect($route3->isActionEnabled('any_action'))->toBeTrue();
});

test('从目录扫描时可以正确读取属性', function () {
    $tempDir = sys_get_temp_dir() . '/luna_test_' . uniqid();
    mkdir($tempDir, 0777, true);
    
    // 创建测试文件
    $fileContent = <<<'PHP'
<?php
namespace TestNamespace;

use Dybasedev\LunaPrototype\Showcase\Attributes\DataTableMeta;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[DataTableMeta(
    title: '扫描测试表',
    description: '从目录扫描的测试表',
    group: 'scanned',
    sortOrder: 200
)]
class ScannedDataTable extends DataTable
{
    public function columns(Request $request): array
    {
        return [];
    }
    
    public function query(Request $request): Builder
    {
        return \Illuminate\Database\Eloquent\Model::query();
    }
}
PHP;
    
    file_put_contents($tempDir . '/ScannedDataTable.php', $fileContent);
    
    try {
        // 先加载类文件
        require_once $tempDir . '/ScannedDataTable.php';
        
        $registry = new DataTableRegistry();
        $registry->registerFromDirectory($tempDir, 'TestNamespace');
        
        $allDataTables = $registry->all();
        $found = false;
        
        foreach ($allDataTables as $dataTable) {
            if ($dataTable['title'] === '扫描测试表') {
                $found = true;
                expect($dataTable)->toMatchArray([
                    'title' => '扫描测试表',
                    'description' => '从目录扫描的测试表',
                    'group' => 'scanned',
                    'sortOrder' => 200,
                ]);
                break;
            }
        }
        
        expect($found)->toBeTrue();
    } finally {
        // 清理
        unlink($tempDir . '/ScannedDataTable.php');
        rmdir($tempDir);
    }
});

test('DataTableMeta属性的所有参数都有默认值', function () {
    $meta = new DataTableMeta(title: '仅标题');
    
    expect($meta->title)->toBe('仅标题');
    expect($meta->description)->toBe('');
    expect($meta->group)->toBe('default');
    expect($meta->sortOrder)->toBe(0);
    expect($meta->visible)->toBeTrue();
});

test('可以在运行时读取属性进行权限检查', function () {
    $dataTable = new AttributeTestDataTable();
    
    // 获取类上的权限属性
    $reflection = new ReflectionClass($dataTable);
    $attributes = $reflection->getAttributes(Permission::class);
    
    expect($attributes)->toHaveCount(1);
    
    $permission = $attributes[0]->newInstance();
    
    // 模拟权限检查逻辑
    $requiredPermissions = $permission->getPermissions();
    expect($requiredPermissions)->toBe(['manage-tests']);
});

