<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;

use Dybasedev\LunaPrototype\Foundation\Installation;
use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AppReinstall extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'app:reinstall 
                            {--skip-backup : 跳过数据备份}
                            {--backup-file= : 指定备份文件名}
                            {--force : 强制重新安装（仅限非生产环境）}
                            {--restore-from= : 从指定备份文件恢复数据}
                            {--modules= : 仅重新安装指定模块（逗号分隔）}
                            {--preserve= : 保留指定数据表（逗号分隔）}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '重新安装应用程序（谨慎使用）';

    /**
     * 安装标识文件路径
     *
     * @var string
     */
    private string $installMarkerFile = '.luna-installed';

    /**
     * 备份文件路径
     *
     * @var string|null
     */
    private ?string $backupFile = null;

    /**
     * 当前安装状态
     *
     * @var array
     */
    private array $installationStatus = [];

    /**
     * 执行命令
     *
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        // 1. 安装状态检测（提前检测，避免环境验证的干扰）
        $this->installationStatus = $this->checkInstallationStatus();
        
        if (!$this->installationStatus['is_installed']) {
            $this->error('❌ 应用尚未安装，请使用 app:install 命令进行首次安装');
            return 1;
        }
        
        $this->info('=====================================');
        $this->info('       应用程序重新安装工具');
        $this->info('=====================================');
        $this->newLine();

        // 2. 环境检测
        if (!$this->validateEnvironment()) {
            return 1;
        }
        
        // 显示安装状态
        $this->displayInstallationStatus();

        // 3. 用户确认
        if (!$this->getUserConfirmation()) {
            $this->info('已取消重新安装');
            return 0;
        }

        // 4. 记录操作日志
        $this->logReinstallation('started');

        try {
            // 5. 执行备份
            if (!$this->option('skip-backup')) {
                $this->performBackup();
            }

            // 6. 执行重新安装
            $this->executeReinstall();

            // 7. 恢复数据（如果指定）
            if ($restoreFile = $this->option('restore-from')) {
                $this->restoreFromBackup($restoreFile);
            }

            // 8. 完成
            $this->displayCompletionInfo();
            $this->logReinstallation('completed');

            return 0;

        } catch (Throwable $e) {
            $this->error('❌ 重新安装失败: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            
            $this->logReinstallation('failed', ['error' => $e->getMessage()]);
            
            // 尝试回滚
            $this->attemptRollback();
            
            return 1;
        }
    }

    /**
     * 验证环境
     *
     * @return bool
     */
    private function validateEnvironment(): bool
    {
        $environment = app()->environment();
        
        $this->info("当前环境: <fg=yellow>{$environment}</>");
        $this->newLine();

        // 生产环境检测
        if ($environment === 'production' || $environment === 'prod') {
            $this->warn('⚠️  警告：您正在生产环境中执行重新安装操作！');
            $this->warn('⚠️  这将删除所有现有数据，此操作不可逆！');
            $this->newLine();

            // 检查是否使用了 --force 参数
            if ($this->option('force')) {
                $this->error('❌ 生产环境不允许使用 --force 参数');
                $this->error('为了数据安全，生产环境需要手动确认每个步骤');
                return false;
            }

            // 第一次确认
            if (!$this->confirm('您确定要在生产环境中重新安装吗？')) {
                return false;
            }

            // 第二次确认（输入确认短语）
            $this->warn('请输入确认短语以继续');
            $confirmation = $this->ask('请输入 "DELETE ALL DATA" 以确认');
            
            if ($confirmation !== 'DELETE ALL DATA') {
                $this->error('❌ 确认短语不匹配，操作已取消');
                return false;
            }

            // 最终确认
            $this->warn('这是最后一次确认机会！');
            if (!$this->confirm('您真的要删除所有数据并重新安装吗？', false)) {
                return false;
            }

            // 等待倒计时
            $this->warn('操作将在 5 秒后开始，按 Ctrl+C 取消...');
            for ($i = 5; $i > 0; $i--) {
                $this->comment("  {$i}...");
                sleep(1);
            }
        }

        return true;
    }

    /**
     * 检查安装状态
     *
     * @return array
     */
    private function checkInstallationStatus(): array
    {
        $status = [
            'marker_exists' => File::exists(base_path($this->installMarkerFile)),
            'has_database_tables' => $this->hasDatabaseTables(),
            'has_configurations' => $this->hasConfigurations(),
            'installed_version' => $this->getInstalledVersion(),
            'current_version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'is_installed' => false,
        ];

        // 判断是否已安装
        $status['is_installed'] = $status['marker_exists'] || 
                                  $status['has_database_tables'] || 
                                  $status['has_configurations'];

        return $status;
    }

    /**
     * 检查是否有数据库表
     *
     * @return bool
     */
    private function hasDatabaseTables(): bool
    {
        try {
            return Schema::hasTable('configurations') || 
                   Schema::hasTable('users') || 
                   Schema::hasTable('migrations');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查是否有配置数据
     *
     * @return bool
     */
    private function hasConfigurations(): bool
    {
        try {
            if (!Schema::hasTable('configurations')) {
                return false;
            }
            
            return DB::table('configurations')->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取已安装的版本
     *
     * @return string|null
     */
    private function getInstalledVersion(): ?string
    {
        $markerPath = base_path($this->installMarkerFile);
        
        if (!File::exists($markerPath)) {
            return null;
        }

        try {
            $data = json_decode(File::get($markerPath), true);
            return $data['version'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 显示安装状态
     *
     * @return void
     */
    private function displayInstallationStatus(): void
    {
        $this->info('📊 当前安装状态:');
        $this->table(
            ['检查项', '状态'],
            [
                ['安装标识文件', $this->installationStatus['marker_exists'] ? '✅ 存在' : '❌ 不存在'],
                ['数据库表', $this->installationStatus['has_database_tables'] ? '✅ 已创建' : '❌ 未创建'],
                ['配置数据', $this->installationStatus['has_configurations'] ? '✅ 已配置' : '❌ 未配置'],
                ['已安装版本', $this->installationStatus['installed_version'] ?: 'N/A'],
                ['当前版本', $this->installationStatus['current_version']],
            ]
        );
        $this->newLine();
    }

    /**
     * 获取用户确认
     *
     * @return bool
     */
    private function getUserConfirmation(): bool
    {
        // 如果使用了 --force 参数（非生产环境）
        if ($this->option('force') && app()->environment() !== 'production') {
            $this->info('使用 --force 参数，跳过确认步骤');
            return true;
        }

        $this->warn('⚠️  重新安装将执行以下操作:');
        $this->line('  • 清空所有数据库表');
        $this->line('  • 重新运行所有迁移');
        $this->line('  • 重新执行所有安装器');
        
        if ($modules = $this->option('modules')) {
            $this->line('  • 仅重新安装模块: ' . $modules);
        }
        
        if ($preserve = $this->option('preserve')) {
            $this->line('  • 保留数据表: ' . $preserve);
        }
        
        $this->newLine();

        return $this->confirm('确定要继续吗？');
    }

    /**
     * 执行备份
     *
     * @return void
     * @throws Throwable
     */
    private function performBackup(): void
    {
        $this->info('📦 正在备份数据...');
        
        try {
            /** @var LunaApplication $lunaApp */
            $lunaApp = $this->laravel->make('luna');
            
            // 获取可备份的对象
            $backupableObjects = $lunaApp->getBackupableObjects();
            
            if (empty($backupableObjects)) {
                $this->comment('没有找到需要备份的数据');
                return;
            }

            // 选择要备份的对象
            $selectedObjects = $this->selectBackupObjects($backupableObjects);
            
            if (empty($selectedObjects)) {
                $this->comment('未选择任何备份对象');
                return;
            }

            // 生成备份文件名
            $this->backupFile = $this->option('backup-file') ?: sprintf(
                'reinstall-backup-%s-%s.dat',
                app()->environment(),
                now()->format('Y-m-d-His')
            );
            
            $backupPath = storage_path('app/luna-backups/' . $this->backupFile);
            
            // 确保备份目录存在
            File::ensureDirectoryExists(dirname($backupPath));
            
            // 执行备份
            $this->info('正在备份 ' . count($selectedObjects) . ' 个对象...');
            
            $backupData = $lunaApp->exportBackup($selectedObjects);
            File::put($backupPath, $backupData);
            
            // 验证备份
            if (!File::exists($backupPath) || filesize($backupPath) === 0) {
                throw new RuntimeException('备份文件创建失败');
            }
            
            $this->info("✅ 备份成功: {$backupPath}");
            $this->info("   文件大小: " . $this->formatBytes(filesize($backupPath)));
            
        } catch (Throwable $e) {
            $this->error('❌ 备份失败: ' . $e->getMessage());
            
            if (!$this->confirm('备份失败，是否继续重新安装？')) {
                throw $e;
            }
        }
    }

    /**
     * 选择要备份的对象
     *
     * @param array $backupableObjects
     * @return array
     */
    private function selectBackupObjects(array $backupableObjects): array
    {
        // 非交互模式或使用 --force，备份所有
        if (!$this->input->isInteractive() || $this->option('force')) {
            return $backupableObjects;
        }

        $this->info('发现以下可备份对象:');
        foreach ($backupableObjects as $index => $class) {
            $this->line(sprintf('  [%d] %s', $index + 1, class_basename($class)));
        }
        $this->newLine();

        // 询问是否备份全部
        if ($this->confirm('是否备份所有对象？', true)) {
            return $backupableObjects;
        }

        // 逐个询问
        $selectedObjects = [];
        foreach ($backupableObjects as $class) {
            if ($this->confirm("备份 " . class_basename($class) . "？", true)) {
                $selectedObjects[] = $class;
            }
        }

        return $selectedObjects;
    }

    /**
     * 执行重新安装
     *
     * @return void
     * @throws Throwable
     */
    private function executeReinstall(): void
    {
        $this->info('🔧 开始重新安装...');
        $this->newLine();

        // 1. 清理数据库
        $this->cleanDatabase();

        // 2. 重新运行迁移
        $this->info('运行数据库迁移...');
        $this->call('migrate:fresh', [
            '--force' => true,
            '--seed' => false,
        ]);

        // 3. 执行安装器
        $this->executeInstallers();

        // 4. 更新安装标识
        $this->updateInstallationMarker();

        // 5. 清理缓存
        $this->clearAllCaches();

        $this->info('✅ 重新安装完成！');
    }

    /**
     * 清理数据库
     *
     * @return void
     */
    private function cleanDatabase(): void
    {
        $this->info('清理数据库...');

        // 获取要保留的表
        $preserveTables = [];
        if ($preserve = $this->option('preserve')) {
            $preserveTables = array_map('trim', explode(',', $preserve));
            $this->info('保留数据表: ' . implode(', ', $preserveTables));
        }

        // 删除安装标识文件
        if (File::exists(base_path($this->installMarkerFile))) {
            File::delete(base_path($this->installMarkerFile));
            $this->comment('已删除安装标识文件');
        }
    }

    /**
     * 执行安装器
     *
     * @return void
     * @throws Throwable
     */
    private function executeInstallers(): void
    {
        $this->info('执行安装器...');

        DB::transaction(function () {
            Model::unguarded(function () {
                $configure = $this->laravel->make(LunaApplicationConfigure::class);
                
                // 获取要安装的模块
                $installations = $configure->installations;
                
                // 如果指定了模块，过滤安装器
                if ($modules = $this->option('modules')) {
                    $this->info('仅重新安装模块: ' . $modules);
                    $selectedModules = array_map('trim', explode(',', $modules));
                    $installations = $this->filterInstallations($installations, $selectedModules);
                }

                // 解析依赖关系
                $installations = $this->resolveInstallationDependencies($installations);
                
                $this->info(sprintf('发现 %d 个安装器', count($installations)));

                foreach ($installations as $index => $installation) {
                    $this->comment(sprintf(
                        "\n[%d/%d] 执行: %s",
                        $index + 1,
                        count($installations),
                        class_basename($installation)
                    ));
                    
                    /** @var Installation $instance */
                    $instance = $this->laravel->make($installation);
                    $instance->withOutput($this->output)->install();
                    
                    $this->info("✓ 完成");
                }
            });
        });
    }

    /**
     * 过滤安装器
     *
     * @param array $installations
     * @param array $selectedModules
     * @return array
     */
    private function filterInstallations(array $installations, array $selectedModules): array
    {
        return array_filter($installations, function ($installation) use ($selectedModules) {
            $basename = class_basename($installation);
            foreach ($selectedModules as $module) {
                if (stripos($basename, $module) !== false) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * 解析安装器依赖
     *
     * @param array $installations
     * @return array
     * @throws Throwable
     */
    private function resolveInstallationDependencies(array $installations): array
    {
        $resolved = [];
        $resolving = [];
        
        foreach ($installations as $installation) {
            $this->resolveDependency($installation, $resolved, $resolving);
        }
        
        return array_values($resolved);
    }

    /**
     * 递归解析依赖
     *
     * @param string $installation
     * @param array $resolved
     * @param array $resolving
     * @throws Throwable
     */
    private function resolveDependency(string $installation, array &$resolved, array &$resolving): void
    {
        if (isset($resolved[$installation])) {
            return;
        }
        
        if (isset($resolving[$installation])) {
            throw new RuntimeException("循环依赖: " . implode(' -> ', array_keys($resolving)) . ' -> ' . $installation);
        }
        
        $resolving[$installation] = true;
        
        /** @var Installation $instance */
        $instance = $this->laravel->make($installation);
        $dependencies = $instance->getDependencies();
        
        foreach ($dependencies as $dependency) {
            $this->resolveDependency($dependency, $resolved, $resolving);
        }
        
        $resolved[$installation] = $installation;
        unset($resolving[$installation]);
    }

    /**
     * 从备份恢复
     *
     * @param string $backupFile
     * @return void
     * @throws Throwable
     */
    private function restoreFromBackup(string $backupFile): void
    {
        $this->info('📥 从备份恢复数据...');
        
        $backupPath = storage_path('app/luna-backups/' . $backupFile);
        
        if (!File::exists($backupPath)) {
            throw new RuntimeException("备份文件不存在: {$backupPath}");
        }

        try {
            /** @var LunaApplication $lunaApp */
            $lunaApp = $this->laravel->make('luna');
            
            $backupData = File::get($backupPath);
            $lunaApp->importBackup($backupData);
            
            $this->info('✅ 数据恢复成功');
            
        } catch (Throwable $e) {
            $this->error('❌ 恢复失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新安装标识
     *
     * @return void
     */
    private function updateInstallationMarker(): void
    {
        $markerPath = base_path($this->installMarkerFile);
        
        $installData = [
            'installed_at' => now()->toIso8601String(),
            'reinstalled_at' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'backup_file' => $this->backupFile,
        ];
        
        File::put($markerPath, json_encode($installData, JSON_PRETTY_PRINT));
        
        $this->comment('已更新安装标识文件');
    }

    /**
     * 清理所有缓存
     *
     * @return void
     */
    private function clearAllCaches(): void
    {
        $this->info('清理缓存...');
        
        $this->call('cache:clear', ['--quiet' => true]);
        $this->call('config:clear', ['--quiet' => true]);
        $this->call('route:clear', ['--quiet' => true]);
        $this->call('view:clear', ['--quiet' => true]);
        
        $this->comment('缓存已清理');
    }

    /**
     * 尝试回滚
     *
     * @return void
     */
    private function attemptRollback(): void
    {
        $this->warn('尝试回滚...');
        
        if ($this->backupFile) {
            $this->info('发现备份文件，您可以使用以下命令恢复:');
            $this->line("  php artisan app:reinstall --restore-from={$this->backupFile}");
        }
    }

    /**
     * 显示完成信息
     *
     * @return void
     */
    private function displayCompletionInfo(): void
    {
        $this->newLine();
        $this->info('=====================================');
        $this->info('        重新安装完成！');
        $this->info('=====================================');
        $this->newLine();
        
        if ($this->backupFile) {
            $this->info('备份文件: ' . $this->backupFile);
        }
        
        $this->info('环境: ' . app()->environment());
        $this->info('版本: ' . config('app.version', '1.0.0'));
        $this->info('时间: ' . now()->format('Y-m-d H:i:s'));
        
        $this->newLine();
        $this->comment('请记得重新配置必要的设置');
    }

    /**
     * 记录重新安装日志
     *
     * @param string $status
     * @param array $extra
     * @return void
     */
    private function logReinstallation(string $status, array $extra = []): void
    {
        $logData = [
            'status' => $status,
            'environment' => app()->environment(),
            'user' => get_current_user(),
            'timestamp' => now()->toIso8601String(),
            'options' => $this->options(),
            'backup_file' => $this->backupFile,
            ...$extra,
        ];

        try {
            // 尝试写入专门的重新安装日志
            if (config('logging.channels.reinstall')) {
                Log::channel('reinstall')->info("Reinstallation {$status}", $logData);
            } else {
                // 写入默认日志
                Log::info("Reinstallation {$status}", $logData);
            }
            
            // 同时写入文件日志
            $logFile = storage_path('logs/reinstall-' . date('Y-m-d') . '.log');
            File::append($logFile, json_encode($logData) . PHP_EOL);
            
        } catch (\Exception $e) {
            // 日志失败不应中断操作
            $this->comment('日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 格式化字节大小
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}