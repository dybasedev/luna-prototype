<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Foundation;

use ArrayIterator;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableDirectoryProvider;
use Dybasedev\LunaPrototype\Foundation\BackupableManualProvider;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Iterator;
use Dybasedev\LunaPrototype\Tests\TestCase;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;

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

    public static function backupDatasourceIterator(): Iterator
    {
        return new ArrayIterator(self::$data);
    }

    public static function recoverFromBackupIterator(Iterator $backup): void
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

/**
 * 可备份功能测试
 */
class BackupableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试表
        $this->createTestTables();

        // 插入测试数据
        $this->seedTestData();
    }

    protected function createTestTables(): void
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

    protected function seedTestData(): void
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

    /**
     * 测试 BackupableModel trait 的基本功能
     */
    public function test_backupable_model_trait()
    {
        // 测试 NamedId 模型
        $this->assertEquals('name', TestConfigModel::getBackupableRelationKey());
        $this->assertEquals('test_configs', TestConfigModel::getBackupableName());

        // 测试自增主键模型
        $this->assertEquals('code', TestProductModel::getBackupableRelationKey());
        $this->assertEquals('test_products', TestProductModel::getBackupableName());

        // 测试依赖关系
        $this->assertEquals([], TestConfigModel::getBackupableDependencies());
        $this->assertEquals([TestConfigModel::class], TestProductModel::getBackupableDependencies());
    }

    /**
     * 测试数据备份功能
     */
    public function test_backup_data_export()
    {
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
        $this->assertIsString($backup);
        $this->assertNotEmpty($backup);

        // 解析备份信息
        $info = $app->getBackupInfo($backup);

        $this->assertEquals('1.0', $info['version']);
        $this->assertCount(2, $info['objects']);

        // 验证对象顺序（依赖关系）
        $this->assertEquals(TestConfigModel::class, $info['objects'][0]['class']);
        $this->assertEquals(TestProductModel::class, $info['objects'][1]['class']);

        // 验证数据数量
        $this->assertEquals(2, $info['objects'][0]['count']); // 2个配置
        $this->assertEquals(2, $info['objects'][1]['count']); // 2个产品
    }

    /**
     * 测试数据恢复功能
     */
    public function test_backup_data_import()
    {
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
        $this->assertEquals(0, TestConfigModel::count());
        $this->assertEquals(0, TestProductModel::count());

        // 恢复备份
        $result = $app->importBackup($backup);

        // 验证恢复结果
        $this->assertCount(2, $result['success']);
        $this->assertEmpty($result['failed']);
        $this->assertEmpty($result['skipped']);

        // 验证数据已恢复
        $this->assertEquals(2, TestConfigModel::count());
        $this->assertEquals(2, TestProductModel::count());

        // 验证具体数据
        $config = TestConfigModel::where('name', 'site_config')->first();
        $this->assertNotNull($config);
        $this->assertEquals(['theme' => 'dark', 'language' => 'zh-CN'], $config->value);

        $product = TestProductModel::where('code', 'PROD001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('测试产品1', $product->name);
        $this->assertEquals(99.99, $product->price);
    }

    /**
     * 测试选择性备份和恢复
     */
    public function test_selective_backup_and_restore()
    {
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
        $this->assertCount(1, $info['objects']);
        $this->assertEquals(TestConfigModel::class, $info['objects'][0]['class']);

        // 清空所有数据
        TestConfigModel::query()->delete();
        TestProductModel::query()->delete();

        // 只恢复配置数据
        $result = $app->importBackup($backup);

        $this->assertCount(1, $result['success']);
        $this->assertEquals(2, TestConfigModel::count());
        $this->assertEquals(0, TestProductModel::count()); // 产品数据未恢复
    }

    /**
     * 测试非压缩和非 base64 编码的备份
     */
    public function test_raw_backup_format()
    {
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
        $this->assertIsArray($data);
        $this->assertEquals('1.0', $data['version']);

        // 恢复备份
        TestCustomBackupable::clearData();
        
        $result = $app->importBackup($backup, [
            'compressed' => false,
            'base64' => false
        ]);

        $this->assertCount(1, $result['success']);
        $this->assertCount(2, TestCustomBackupable::getData());
    }

    /**
     * 测试备份提供者
     */
    public function test_backup_providers()
    {
        // 手动提供者
        $manualProvider = BackupableManualProvider::create()
            ->register(TestConfigModel::class)
            ->register(TestProductModel::class);

        $this->assertCount(2, $manualProvider->backupableObjects());

        // 测试去重
        $manualProvider->register(TestConfigModel::class);
        $this->assertCount(2, $manualProvider->backupableObjects());

        // 测试移除
        $manualProvider->remove(TestProductModel::class);
        $this->assertCount(1, $manualProvider->backupableObjects());
    }

    /**
     * 测试依赖关系排序
     */
    public function test_dependency_sorting()
    {
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
        $this->assertEquals(TestConfigModel::class, $info['objects'][0]['class']);
        $this->assertEquals(TestProductModel::class, $info['objects'][1]['class']);
    }

    /**
     * 测试错误处理
     */
    public function test_error_handling()
    {
        $app = new LunaApplication(LunaApplicationConfigure::create()->build());

        // 测试无效的 base64 数据（看起来像 base64 但解码后不是有效数据）
        // 这个会在反序列化时失败
        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('Unserialize failed');
        $app->importBackup('aW52YWxpZC1iYXNlNjQtZGF0YQ=='); // 这是 'invalid-base64-data' 的 base64 编码
    }

    /**
     * 测试无效序列化数据的错误处理
     */
    public function test_invalid_serialized_data()
    {
        $app = new LunaApplication(LunaApplicationConfigure::create()->build());

        // 测试无效的序列化数据
        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('Unserialize failed');
        $app->importBackup(base64_encode('invalid-serialized-data'));
    }

    /**
     * 测试版本不匹配
     */
    public function test_version_mismatch()
    {
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

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('备份版本不匹配');
        $app->importBackup($backup);
    }
}