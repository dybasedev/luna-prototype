<?php

use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableManualProvider;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;

uses(RefreshDatabase::class);

/**
 * 测试模型 - 使用 NamedId trait
 */
class TestConfigModel extends Model implements Backupable
{
    use NamedId, BackupableModel;

    protected $table = 'test_configs';
    protected $fillable = ['name', 'value', 'description'];
    protected $casts = ['value' => 'array'];

    public static function getBackupableDependencies(): array
    {
        return [];
    }
}

/**
 * 测试模型 - 使用自增主键
 */
class TestProductModel extends Model implements Backupable
{
    use BackupableModel;

    protected $table = 'test_products';
    protected $fillable = ['code', 'name', 'price', 'category'];

    public static function getBackupableRelationKey(): string|array|null
    {
        return 'code'; // 使用 code 作为关联键
    }

    public static function getBackupableDependencies(): array
    {
        return [TestConfigModel::class]; // 依赖配置表
    }
}

/**
 * 自定义备份对象 - 非模型类
 */
class TestCustomBackupable implements Backupable
{
    private static array $data = [
        ['id' => 1, 'key' => 'setting1', 'value' => 'value1'],
        ['id' => 2, 'key' => 'setting2', 'value' => 'value2'],
    ];

    public static function getBackupableRelationKey(): string|array|null
    {
        return 'key';
    }

    public static function getBackupableName(): string
    {
        return 'custom_settings';
    }

    public static function backupDatasourceIterator(): \Iterator
    {
        return new \ArrayIterator(self::$data);
    }

    public static function recoverFromBackupIterator(\Iterator $backup): void
    {
        self::$data = [];
        foreach ($backup as $item) {
            self::$data[] = $item;
        }
    }

    public static function getBackupableDependencies(): array
    {
        return [];
    }

    public static function getData(): array
    {
        return self::$data;
    }

    public static function clearData(): void
    {
        self::$data = [];
    }
}

beforeEach(function () {
    // 创建测试表
    createTestTables();
    
    // 插入测试数据
    seedTestData();
});

function createTestTables(): void
{
    // 创建配置表
    \Schema::create('test_configs', function ($table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('name')->unique();
        $table->json('value');
        $table->string('description')->default('');
        $table->timestamps();
    });

    // 创建产品表
    \Schema::create('test_products', function ($table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->string('category');
        $table->timestamps();
    });
}

function seedTestData(): void
{
    // 添加配置数据
    TestConfigModel::create([
        'name' => 'site_config',
        'value' => ['theme' => 'dark', 'language' => 'zh-CN'],
        'description' => '网站配置'
    ]);

    TestConfigModel::create([
        'name' => 'api_config',
        'value' => ['timeout' => 30, 'retry' => 3],
        'description' => 'API配置'
    ]);

    // 添加产品数据
    TestProductModel::create([
        'code' => 'PROD001',
        'name' => '测试产品1',
        'price' => 99.99,
        'category' => '电子产品'
    ]);

    TestProductModel::create([
        'code' => 'PROD002',
        'name' => '测试产品2',
        'price' => 199.99,
        'category' => '家电'
    ]);
}

it('测试 BackupableModel trait 的基本功能', function () {
    // 测试 NamedId 模型
    expect(TestConfigModel::getBackupableRelationKey())->toBe('name');
    expect(TestConfigModel::getBackupableName())->toBe('test_configs');

    // 测试自增主键模型
    expect(TestProductModel::getBackupableRelationKey())->toBe('code');
    expect(TestProductModel::getBackupableName())->toBe('test_products');

    // 测试依赖关系
    expect(TestConfigModel::getBackupableDependencies())->toBe([]);
    expect(TestProductModel::getBackupableDependencies())->toBe([TestConfigModel::class]);
});

it('测试数据备份功能', function () {
    // 配置应用
    $configure = LunaApplicationConfigure::create()
        ->registerBackupables([
            TestConfigModel::class,
            TestProductModel::class,
        ])
        ->build();

    $app = new LunaApplication($configure);

    // 导出备份
    $backup = $app->exportBackup();

    // 验证备份数据
    expect($backup)->toBeString();
    expect($backup)->not->toBeEmpty();

    // 解析备份信息
    $info = $app->getBackupInfo($backup);

    expect($info['version'])->toBe('1.0');
    expect($info['objects'])->toHaveCount(2);

    // 验证对象顺序（依赖关系）
    expect($info['objects'][0]['class'])->toBe(TestConfigModel::class);
    expect($info['objects'][1]['class'])->toBe(TestProductModel::class);

    // 验证数据数量
    expect($info['objects'][0]['count'])->toBe(2); // 2个配置
    expect($info['objects'][1]['count'])->toBe(2); // 2个产品
});

it('测试数据恢复功能', function () {
    // 配置应用
    $configure = LunaApplicationConfigure::create()
        ->registerBackupables([
            TestConfigModel::class,
            TestProductModel::class,
        ])
        ->build();

    $app = new LunaApplication($configure);

    // 导出备份
    $backup = $app->exportBackup();

    // 清空数据
    TestConfigModel::query()->delete();
    TestProductModel::query()->delete();

    // 验证数据已清空
    expect(TestConfigModel::count())->toBe(0);
    expect(TestProductModel::count())->toBe(0);

    // 恢复备份
    $result = $app->importBackup($backup);

    // 验证恢复结果
    expect($result['success'])->toHaveCount(2);
    expect($result['failed'])->toBeEmpty();
    expect($result['skipped'])->toBeEmpty();

    // 验证数据已恢复
    expect(TestConfigModel::count())->toBe(2);
    expect(TestProductModel::count())->toBe(2);

    // 验证具体数据
    $config = TestConfigModel::where('name', 'site_config')->first();
    expect($config)->not->toBeNull();
    expect($config->value)->toBe(['theme' => 'dark', 'language' => 'zh-CN']);

    $product = TestProductModel::where('code', 'PROD001')->first();
    expect($product)->not->toBeNull();
    expect($product->name)->toBe('测试产品1');
    expect($product->price)->toBe('99.99');
});

it('测试选择性备份和恢复', function () {
    // 配置应用
    $configure = LunaApplicationConfigure::create()
        ->registerBackupables([
            TestConfigModel::class,
            TestProductModel::class,
        ])
        ->build();

    $app = new LunaApplication($configure);

    // 只导出配置数据
    $backup = $app->exportBackup([
        'objects' => [TestConfigModel::class]
    ]);

    $info = $app->getBackupInfo($backup);
    expect($info['objects'])->toHaveCount(1);
    expect($info['objects'][0]['class'])->toBe(TestConfigModel::class);

    // 清空所有数据
    TestConfigModel::query()->delete();
    TestProductModel::query()->delete();

    // 只恢复配置数据
    $result = $app->importBackup($backup);

    expect($result['success'])->toHaveCount(1);
    expect(TestConfigModel::count())->toBe(2);
    expect(TestProductModel::count())->toBe(0); // 产品数据未恢复
});

it('测试非压缩和非 base64 编码的备份', function () {
    $configure = LunaApplicationConfigure::create()
        ->registerBackupable(TestCustomBackupable::class)
        ->build();

    $app = new LunaApplication($configure);

    // 导出原始格式备份
    $backup = $app->exportBackup([
        'compress' => false,
        'base64' => false
    ]);

    // 验证是序列化的数据
    $data = unserialize($backup);
    expect($data)->toBeArray();
    expect($data['version'])->toBe('1.0');

    // 恢复备份
    TestCustomBackupable::clearData();
    
    $result = $app->importBackup($backup, [
        'compressed' => false,
        'base64' => false
    ]);

    expect($result['success'])->toHaveCount(1);
    expect(TestCustomBackupable::getData())->toHaveCount(2);
});

it('测试备份提供者', function () {
    // 手动提供者
    $manualProvider = BackupableManualProvider::create()
        ->register(TestConfigModel::class)
        ->register(TestProductModel::class);

    expect($manualProvider->backupableObjects())->toHaveCount(2);

    // 测试去重
    $manualProvider->register(TestConfigModel::class);
    expect($manualProvider->backupableObjects())->toHaveCount(2);

    // 测试移除
    $manualProvider->remove(TestProductModel::class);
    expect($manualProvider->backupableObjects())->toHaveCount(1);
});

it('测试依赖关系排序', function () {
    // 创建有循环依赖的测试类
    $configure = LunaApplicationConfigure::create()
        ->registerBackupables([
            TestProductModel::class, // 依赖 TestConfigModel
            TestConfigModel::class,  // 无依赖
        ])
        ->build();

    $app = new LunaApplication($configure);

    // 导出备份
    $backup = $app->exportBackup();
    $info = $app->getBackupInfo($backup);

    // 验证排序结果：配置应该在产品之前
    expect($info['objects'][0]['class'])->toBe(TestConfigModel::class);
    expect($info['objects'][1]['class'])->toBe(TestProductModel::class);
});

it('测试错误处理', function () {
    $app = new LunaApplication(LunaApplicationConfigure::create()->build());

    // 测试无效的 base64 数据（看起来像 base64 但解码后不是有效数据）
    // 这个会在反序列化时失败
    expect(function () use ($app) {
        $app->importBackup('aW52YWxpZC1iYXNlNjQtZGF0YQ=='); // 这是 'invalid-base64-data' 的 base64 编码
    })->toThrow(LunaException::class, 'Unserialize failed');
});

it('测试无效序列化数据的错误处理', function () {
    $app = new LunaApplication(LunaApplicationConfigure::create()->build());

    // 测试无效的序列化数据
    expect(function () use ($app) {
        $app->importBackup(base64_encode('invalid-serialized-data'));
    })->toThrow(LunaException::class, 'Unserialize failed');
});

it('测试版本不匹配', function () {
    $app = new LunaApplication(LunaApplicationConfigure::create()->build());

    // 创建一个版本不匹配的备份数据
    $backupData = [
        'version' => '0.1', // 错误的版本
        'created_at' => now()->toIso8601String(),
        'app_name' => 'test',
        'app_env' => 'testing',
        'objects' => [],
    ];

    $backup = base64_encode(serialize($backupData));

    expect(function () use ($app, $backup) {
        $app->importBackup($backup);
    })->toThrow(LunaException::class, '备份版本不匹配');
});