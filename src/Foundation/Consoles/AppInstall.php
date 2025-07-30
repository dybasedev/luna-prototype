<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;

use Dybasedev\LunaPrototype\Foundation\Installation;
use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class AppInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install 
                            {--force : 强制重新安装}
                            {--skip-backup : 重新安装时跳过备份}
                            {--backup-file= : 指定备份文件名}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install application';
    
    /**
     * 安装标识文件路径
     *
     * @var string
     */
    private string $installMarkerFile = '.luna-installed';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->info(sprintf('=> Initialize application [%s]', config('app.name')));

        // 检查是否已安装
        if ($this->isInstalled() && !$this->option('force')) {
            $this->warn('应用程序已经安装。');
            
            if (!$this->confirm('是否要重新安装？这将清除现有数据。')) {
                $this->info('安装已取消。');
                return;
            }
            
            // 执行重新安装流程
            $this->handleReinstall();
        }

        // 初始化
        $this->comment('Execute key:generate');
        $this->call('key:generate', ['--no-interaction' => true]);

        $this->comment('Execute migrate');
        $this->call('migrate');

        // 安装
        try {
            DB::transaction(function () {
                Model::unguarded(function () {
                    $configure = $this->laravel->make(LunaApplicationConfigure::class);

                    // 解析安装器的依赖关系
                    $installations = $this->resolveInstallationDependencies($configure->installations);
                    
                    $this->info(sprintf('=> 发现 %d 个安装器（含依赖）', count($installations)));

                    foreach ($installations as $index => $installation) {
                        $this->comment(sprintf("\n[%d/%d] 执行安装器: %s", $index + 1, count($installations), class_basename($installation)));
                        
                        /** @var Installation $instance */
                        $instance = $this->laravel->make($installation);
                        $instance->withOutput($this->output)->install();
                        
                        $this->info("✓ 完成");
                    }
                });
            });
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());
            $this->error($throwable->getTraceAsString());

            $this->error('=> Installation failed.');
            return;
        }

        // 清理缓存
        $this->laravel->make('cache')->clear();
        
        // 创建安装标识文件
        $this->createInstallMarker();
        
        $this->info('=> 安装完成！');
    }

    /**
     * 解析安装器的依赖关系
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
     * 递归解析单个安装器的依赖
     * 
     * @param string $installation
     * @param array $resolved
     * @param array $resolving
     * @throws Throwable
     */
    private function resolveDependency(string $installation, array &$resolved, array &$resolving): void
    {
        // 如果已经解析过，直接返回
        if (isset($resolved[$installation])) {
            return;
        }
        
        // 检测循环依赖
        if (isset($resolving[$installation])) {
            throw new RuntimeException("Circular dependency detected: " . implode(' -> ', array_keys($resolving)) . ' -> ' . $installation);
        }
        
        // 标记为正在解析
        $resolving[$installation] = true;
        
        // 获取安装器实例并解析其依赖
        /** @var Installation $instance */
        $instance = $this->laravel->make($installation);
        $dependencies = $instance->getDependencies();
        
        // 递归解析依赖
        foreach ($dependencies as $dependency) {
            $this->resolveDependency($dependency, $resolved, $resolving);
        }
        
        // 将当前安装器添加到已解析列表
        $resolved[$installation] = $installation;
        
        // 从正在解析列表中移除
        unset($resolving[$installation]);
    }
    
    /**
     * 检查应用是否已安装
     * 
     * @return bool
     */
    private function isInstalled(): bool
    {
        return File::exists(base_path($this->installMarkerFile));
    }
    
    /**
     * 创建安装标识文件
     * 
     * @return void
     */
    private function createInstallMarker(): void
    {
        $markerPath = base_path($this->installMarkerFile);
        
        // 创建安装标识文件
        $installData = [
            'installed_at' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
        ];
        
        File::put($markerPath, json_encode($installData, JSON_PRETTY_PRINT));
        
        // 检查并更新 .gitignore
        $this->updateGitignore();
    }
    
    /**
     * 更新 .gitignore 文件
     * 
     * @return void
     */
    private function updateGitignore(): void
    {
        $gitignorePath = base_path('.gitignore');
        
        if (!File::exists($gitignorePath)) {
            return;
        }
        
        $gitignoreContent = File::get($gitignorePath);
        $markerPattern = '/' . $this->installMarkerFile;
        
        // 检查是否已经包含该规则
        if (!str_contains($gitignoreContent, $markerPattern)) {
            // 添加到 .gitignore
            $gitignoreContent = rtrim($gitignoreContent) . "\n\n# Luna installation marker\n" . $markerPattern . "\n";
            File::put($gitignorePath, $gitignoreContent);
            
            $this->comment('.gitignore 已更新，添加了安装标识文件');
        }
    }
    
    /**
     * 处理重新安装
     * 
     * @return void
     * @throws Throwable
     */
    private function handleReinstall(): void
    {
        if (!$this->option('skip-backup')) {
            $this->performBackup();
        }
        
        // 清理现有数据
        $this->warn('正在清理现有数据...');
        
        // 重置数据库
        $this->call('migrate:fresh', ['--force' => true]);
        
        // 删除安装标识文件
        File::delete(base_path($this->installMarkerFile));
    }
    
    /**
     * 执行备份
     * 
     * @return void
     * @throws Throwable
     */
    private function performBackup(): void
    {
        try {
            $this->info('正在备份现有数据...');
            
            /** @var LunaApplication $lunaApp */
            $lunaApp = $this->laravel->make('luna');
            
            // 检查是否有可备份的对象
            $backupableObjects = $lunaApp->getBackupableObjects();
            
            if (empty($backupableObjects)) {
                $this->comment('没有找到需要备份的数据。');
                return;
            }
            
            // 询问用户要备份哪些对象
            $selectedObjects = [];
            if ($this->input->isInteractive()) {
                $this->info('发现以下可备份对象：');
                foreach ($backupableObjects as $class => $object) {
                    if ($this->confirm("是否备份 " . class_basename($class) . "？", true)) {
                        $selectedObjects[] = $class;
                    }
                }
            } else {
                // 非交互模式下备份所有对象
                $selectedObjects = array_keys($backupableObjects);
            }
            
            if (empty($selectedObjects)) {
                $this->comment('未选择任何备份对象。');
                return;
            }
            
            // 执行备份
            $backupFile = $this->option('backup-file') ?: 'backup-' . date('Y-m-d-His') . '.dat';
            $backupPath = storage_path('app/luna-backups/' . $backupFile);
            
            // 确保备份目录存在
            File::ensureDirectoryExists(dirname($backupPath));
            
            // 生成备份数据
            $backupData = $lunaApp->exportBackup($selectedObjects);
            File::put($backupPath, $backupData);
            
            $this->info("备份已保存到: " . $backupPath);
            
        } catch (Throwable $e) {
            $this->error('备份失败: ' . $e->getMessage());
            
            if (!$this->confirm('备份失败，是否继续重新安装？')) {
                throw $e;
            }
        }
    }
}
