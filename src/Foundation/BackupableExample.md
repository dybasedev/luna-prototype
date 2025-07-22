# 可备份对象使用示例

## 1. 为现有模型添加备份功能

### 使用 NamedId 的模型

```php
<?php

namespace App\Models;

use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;

class Handler extends Model implements Backupable
{
    use NamedId, BackupableModel;

    protected $table = 'luna_handlers';
    protected $fillable = ['name', 'group_id', 'display_name', 'handler', 'config'];
    protected $casts = ['config' => 'array'];

    // 由于使用了 NamedId，默认会使用 'name' 作为关联键
    // 不需要重写 getBackupableRelationKey 方法
}
```

### 使用自增主键的模型

```php
<?php

namespace App\Models;

use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Backupable
{
    use BackupableModel;

    protected $fillable = ['code', 'name', 'price', 'description'];

    // 重写关联键，使用 code 字段而不是自增 ID
    public static function getBackupableRelationKey(): string|array|null
    {
        return 'code';
    }

    // 定义依赖关系
    public static function getBackupableDependencies(): array
    {
        return [
            Category::class, // 产品依赖分类
            Brand::class,    // 产品依赖品牌
        ];
    }

    // 自定义备份前的数据处理
    protected static function processBackupData(array $data): array
    {
        // 移除敏感信息
        unset($data['internal_notes']);
        return $data;
    }
}
```

### 使用复合键的模型

```php
class Translation extends Model implements Backupable
{
    use BackupableModel;

    // 使用语言和键的组合作为唯一标识
    public static function getBackupableRelationKey(): string|array|null
    {
        return ['language', 'key'];
    }
}
```

## 2. 配置备份对象

### 在服务提供者中配置

```php
<?php

namespace App\Providers;

use App\Models\Handler;
use App\Models\BusinessEvent;
use App\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\BackupableDirectoryProvider;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;

class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 配置应用程序
        $this->registerModule(
            LunaApplicationConfigure::create()
                // 手动注册单个备份对象
                ->registerBackupable(Handler::class)
                ->registerBackupable(BusinessEvent::class)
                ->registerBackupable(Configuration::class)
                
                // 批量注册备份对象
                ->registerBackupables([
                    \App\Models\AssetType::class,
                    \App\Models\UnitDefinition::class,
                    \App\Models\ScheduleConfig::class,
                ])
                
                // 通过目录扫描自动注册
                ->addBackupableDirectory(
                    app_path('Models/Settings'),
                    'App\\Models\\Settings'
                )
                
                // 使用自定义提供者
                ->addBackupableProvider(
                    BackupableDirectoryProvider::path(app_path('Models'))
                        ->withNamespace('App\\Models')
                )
                
                ->build()
        );
    }
}
```

## 3. 执行备份和恢复

### 基本使用

```php
// 获取应用实例
$app = luna_app();

// 导出所有备份数据
$backupData = $app->exportBackup();

// 保存到文件
file_put_contents('backup.dat', $backupData);

// 从文件恢复
$backupData = file_get_contents('backup.dat');
$result = $app->importBackup($backupData);

// 查看恢复结果
echo "成功恢复: " . count($result['success']) . " 个对象\n";
echo "失败: " . count($result['failed']) . " 个对象\n";
```

### 选择性备份

```php
// 只备份特定的对象
$backupData = $app->exportBackup([
    'objects' => [
        Handler::class,
        Configuration::class,
    ]
]);

// 导出原始格式（用于生成下载文件）
$rawBackup = $app->exportBackup([
    'compress' => true,   // 压缩但不 base64
    'base64' => false,    // 返回二进制数据
]);

// 生成下载响应
return response($rawBackup)
    ->header('Content-Type', 'application/octet-stream')
    ->header('Content-Disposition', 'attachment; filename="backup-' . date('Y-m-d') . '.dat"');
```

### 查看备份信息

```php
// 不执行恢复，只查看备份内容
$info = $app->getBackupInfo($backupData);

echo "备份版本: " . $info['version'] . "\n";
echo "创建时间: " . $info['created_at'] . "\n";
echo "应用名称: " . $info['app_name'] . "\n";
echo "环境: " . $info['app_env'] . "\n";

foreach ($info['objects'] as $object) {
    echo sprintf(
        "- %s (%s): %d 条记录\n",
        $object['name'],
        $object['class'],
        $object['count']
    );
}
```

## 4. 创建自定义备份对象

对于非模型数据，可以自定义实现 Backupable 接口：

```php
<?php

namespace App\Backup;

use Dybasedev\LunaPrototype\Foundation\Backupable;
use Illuminate\Support\Facades\Cache;
use Iterator;
use ArrayIterator;

class CacheSettings implements Backupable
{
    public static function getBackupableRelationKey(): string|array|null
    {
        return 'key';
    }

    public static function getBackupableName(): string
    {
        return 'cache_settings';
    }

    public static function backupDatasourceIterator(): Iterator
    {
        // 获取需要备份的缓存键
        $keys = [
            'app.settings',
            'app.features',
            'app.maintenance',
        ];

        $data = [];
        foreach ($keys as $key) {
            if (Cache::has($key)) {
                $data[] = [
                    'key' => $key,
                    'value' => Cache::get($key),
                    'ttl' => Cache::getStore()->getExpiration($key) - time(),
                ];
            }
        }

        return new ArrayIterator($data);
    }

    public static function recoverFromBackupIterator(Iterator $backup): void
    {
        foreach ($backup as $item) {
            Cache::put(
                $item['key'],
                $item['value'],
                $item['ttl'] ?? 3600
            );
        }
    }

    public static function getBackupableDependencies(): array
    {
        return [];
    }
}
```

## 5. 创建备份管理命令

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupCommand extends Command
{
    protected $signature = 'app:backup 
                            {action : export|import|info}
                            {--file= : 备份文件名}
                            {--objects=* : 要备份的对象类名}';

    protected $description = '管理 Luna 应用备份';

    public function handle()
    {
        $action = $this->argument('action');
        $app = luna_app();

        switch ($action) {
            case 'export':
                $this->exportBackup($app);
                break;
                
            case 'import':
                $this->importBackup($app);
                break;
                
            case 'info':
                $this->showBackupInfo($app);
                break;
                
            default:
                $this->error("未知操作: {$action}");
        }
    }

    private function exportBackup($app)
    {
        $options = [];
        
        if ($objects = $this->option('objects')) {
            $options['objects'] = $objects;
        }

        $this->info('正在导出备份...');
        
        $backup = $app->exportBackup($options);
        
        $filename = $this->option('file') ?? 'backup-' . date('Y-m-d-His') . '.dat';
        Storage::put("backups/{$filename}", $backup);
        
        $this->info("备份已保存到: storage/app/backups/{$filename}");
        
        // 显示备份信息
        $info = $app->getBackupInfo($backup);
        $this->table(
            ['对象', '名称', '记录数'],
            array_map(fn($obj) => [
                $obj['class'],
                $obj['name'],
                $obj['count']
            ], $info['objects'])
        );
    }

    private function importBackup($app)
    {
        $filename = $this->option('file');
        if (!$filename) {
            $this->error('请指定要导入的备份文件');
            return;
        }

        if (!Storage::exists("backups/{$filename}")) {
            $this->error("备份文件不存在: {$filename}");
            return;
        }

        $this->info('正在导入备份...');
        
        $backup = Storage::get("backups/{$filename}");
        $result = $app->importBackup($backup);

        $this->info('导入完成！');
        $this->info('成功: ' . count($result['success']));
        $this->info('失败: ' . count($result['failed']));
        $this->info('跳过: ' . count($result['skipped']));

        if (!empty($result['failed'])) {
            $this->error('失败的对象:');
            foreach ($result['failed'] as $class => $error) {
                $this->error("  - {$class}: {$error}");
            }
        }
    }

    private function showBackupInfo($app)
    {
        $filename = $this->option('file');
        if (!$filename) {
            $this->error('请指定要查看的备份文件');
            return;
        }

        if (!Storage::exists("backups/{$filename}")) {
            $this->error("备份文件不存在: {$filename}");
            return;
        }

        $backup = Storage::get("backups/{$filename}");
        $info = $app->getBackupInfo($backup);

        $this->info('备份信息:');
        $this->info('版本: ' . $info['version']);
        $this->info('创建时间: ' . $info['created_at']);
        $this->info('应用名称: ' . $info['app_name']);
        $this->info('环境: ' . $info['app_env']);
        
        $this->table(
            ['对象', '名称', '记录数'],
            array_map(fn($obj) => [
                $obj['class'],
                $obj['name'],
                $obj['count']
            ], $info['objects'])
        );
    }
}
```

## 6. 最佳实践

1. **定期备份重要数据**
   - 设置定时任务定期导出备份
   - 将备份文件存储到安全的位置

2. **测试恢复流程**
   - 在生产环境使用前，先在测试环境验证
   - 确保所有依赖关系正确配置

3. **版本控制**
   - 记录备份文件的版本信息
   - 在重大更新前后分别备份

4. **安全考虑**
   - 备份文件包含敏感数据，需要妥善保管
   - 考虑对备份文件进行加密
   - 限制备份和恢复操作的权限

5. **性能优化**
   - 大数据量时考虑分批备份
   - 使用队列异步处理备份任务
   - 定期清理过期的备份文件