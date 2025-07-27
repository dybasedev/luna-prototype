<?php

use Dybasedev\LunaPrototype\Showcase\Adapters\AntDesignProAdapter;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTable;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTableRegistry;
use Dybasedev\LunaPrototype\Showcase\LunaShowcase;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;

// 创建测试用的 DataTable 类
class TestDataTable extends DataTable
{
    public function columns(\Illuminate\Http\Request $request): array
    {
        return [];
    }
    
    public function query(\Illuminate\Http\Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return \Illuminate\Database\Eloquent\Model::query();
    }
}

test('配置类可以正确创建', function () {
    $configure = LunaShowcaseConfigure::create();
    
    expect($configure)->toBeInstanceOf(LunaShowcaseConfigure::class);
});

test('模块名称正确返回', function () {
    $configure = new LunaShowcaseConfigure();
    
    expect($configure->name())->toBe('luna.showcase');
});

test('服务提供者返回null', function () {
    $configure = new LunaShowcaseConfigure();
    
    expect($configure->serviceProvider())->toBeNull();
});

test('可以注册单个DataTable', function () {
    $configure = new LunaShowcaseConfigure();
    
    $configure->registerDataTable('users', TestDataTable::class, ['group' => 'admin']);
    
    $registry = $configure->getDataTableRegistry();
    expect($registry)->toBeInstanceOf(DataTableRegistry::class);
});

test('可以批量注册DataTable', function () {
    $configure = new LunaShowcaseConfigure();
    
    $dataTables = [
        'users' => TestDataTable::class,
        'posts' => [
            'class' => TestDataTable::class,
            'group' => 'blog',
            'title' => '文章管理'
        ]
    ];
    
    $configure->registerDataTables($dataTables);
    
    $registry = $configure->getDataTableRegistry();
    expect($registry)->toBeInstanceOf(DataTableRegistry::class);
});

test('可以从目录扫描注册DataTable', function () {
    $configure = new LunaShowcaseConfigure();
    
    // 创建测试目录
    $fixturesDir = __DIR__ . '/Fixtures/DataTables';
    if (!is_dir($fixturesDir)) {
        mkdir($fixturesDir, 0777, true);
    }
    
    try {
        $configure->registerDataTablesFromDirectory(
            $fixturesDir,
            'Dybasedev\\LunaPrototype\\Tests\\Unit\\Showcase\\Fixtures\\DataTables'
        );
        
        $registry = $configure->getDataTableRegistry();
        expect($registry)->toBeInstanceOf(DataTableRegistry::class);
    } finally {
        // 清理测试目录
        if (is_dir($fixturesDir)) {
            rmdir($fixturesDir);
            rmdir(dirname($fixturesDir));
        }
    }
});

test('可以注册自定义适配器', function () {
    $configure = new LunaShowcaseConfigure();
    
    $configure->registerAdapter('custom', 'App\Adapters\CustomAdapter');
    
    // 验证适配器已注册（通过反射访问 protected 属性）
    $reflection = new \ReflectionClass($configure);
    $property = $reflection->getProperty('adapters');
    $property->setAccessible(true);
    $adapters = $property->getValue($configure);
    
    expect($adapters)->toHaveKey('custom');
    expect($adapters['custom'])->toBe('App\Adapters\CustomAdapter');
});

test('可以设置默认适配器', function () {
    $configure = new LunaShowcaseConfigure();
    
    // 先注册一个自定义适配器
    $configure->registerAdapter('custom', AntDesignProAdapter::class);
    
    // 设置为默认
    $configure->setDefaultAdapter('custom');
    
    // 验证默认适配器已更改（通过反射访问 protected 属性）
    $reflection = new \ReflectionClass($configure);
    $property = $reflection->getProperty('defaultAdapter');
    $property->setAccessible(true);
    $defaultAdapter = $property->getValue($configure);
    
    expect($defaultAdapter)->toBe('custom');
});

test('设置不存在的默认适配器会抛出异常', function () {
    $configure = new LunaShowcaseConfigure();
    
    $configure->setDefaultAdapter('non-existent');
})->throws(\InvalidArgumentException::class, "Adapter 'non-existent' not found");

test('获取默认适配器', function () {
    $configure = new LunaShowcaseConfigure();
    
    $adapter = $configure->getAdapter();
    
    expect($adapter)->toBeInstanceOf(AntDesignProAdapter::class);
});

test('获取指定适配器', function () {
    $configure = new LunaShowcaseConfigure();
    
    $adapter = $configure->getAdapter('ant-design-pro');
    
    expect($adapter)->toBeInstanceOf(AntDesignProAdapter::class);
});

test('获取不存在的适配器会抛出异常', function () {
    $configure = new LunaShowcaseConfigure();
    
    $configure->getAdapter('non-existent');
})->throws(\InvalidArgumentException::class, "Adapter 'non-existent' not found");

test('DataTable注册器延迟初始化', function () {
    $configure = new LunaShowcaseConfigure();
    
    // 第一次获取时创建
    $registry1 = $configure->getDataTableRegistry();
    expect($registry1)->toBeInstanceOf(DataTableRegistry::class);
    
    // 第二次获取时返回同一个实例
    $registry2 = $configure->getDataTableRegistry();
    expect($registry2)->toBe($registry1);
});

test('register方法正确注册服务', function () {
    $container = app();
    $configure = new LunaShowcaseConfigure();
    
    $configure->register($container);
    
    // 验证服务已注册
    expect($container->bound('luna.showcase'))->toBeTrue();
    expect($container->bound(LunaShowcase::class))->toBeTrue();
    
    // 验证是单例
    $instance1 = $container->make('luna.showcase');
    $instance2 = $container->make(LunaShowcase::class);
    expect($instance2)->toBe($instance1);
});

test('boot方法初始化DataTableRegistry', function () {
    $container = app();
    $configure = new LunaShowcaseConfigure();
    
    // 注册一些 DataTable
    $configure->registerDataTable('test', TestDataTable::class);
    
    // 执行 boot
    $configure->boot($container);
    
    // 验证 DataTableRegistry 已初始化
    $registry = $configure->getDataTableRegistry();
    expect($registry)->toBeInstanceOf(DataTableRegistry::class);
});