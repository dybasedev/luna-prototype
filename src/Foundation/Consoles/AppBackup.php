<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;

use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 应用备份管理命令
 * 
 * 提供备份数据的导出、导入和查看功能。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Consoles
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class AppBackup extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'app:backup 
                            {action : 执行的操作 (export|import|info)}
                            {--file= : 备份文件名}
                            {--path= : 备份文件路径（默认：storage/app/luna-backups）}
                            {--objects=* : 要备份的对象类名}
                            {--force : 强制导入（忽略版本检查）}
                            {--raw : 导出原始格式（无压缩和 base64）}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '管理 Luna 应用程序的数据备份';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        
        if (!in_array($action, ['export', 'import', 'info'])) {
            $this->error("无效的操作: {$action}");
            $this->info('有效的操作: export, import, info');
            return 1;
        }

        try {
            $app = app('luna');
            
            return match ($action) {
                'export' => $this->exportBackup($app),
                'import' => $this->importBackup($app),
                'info' => $this->showBackupInfo($app),
            };
        } catch (\Exception $e) {
            $this->error('操作失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 导出备份
     */
    protected function exportBackup(LunaApplication $app): int
    {
        $this->info('正在导出备份数据...');
        
        $options = [];
        
        // 处理对象过滤
        if ($objects = $this->option('objects')) {
            $options['objects'] = $objects;
            $this->info('导出对象: ' . implode(', ', $objects));
        }
        
        // 处理原始格式选项
        if ($this->option('raw')) {
            $options['compress'] = true;  // 仍然压缩
            $options['base64'] = false;   // 但不进行 base64 编码
        }
        
        // 执行导出
        $backup = $app->exportBackup($options);
        
        // 确定文件名和路径
        $filename = $this->option('file') ?: 'luna-backup-' . date('Y-m-d-His') . '.dat';
        $path = rtrim($this->option('path') ?: 'luna-backups', '/');
        $fullPath = "{$path}/{$filename}";
        
        // 确保目录存在
        Storage::makeDirectory($path);
        
        // 保存备份文件
        Storage::put($fullPath, $backup);
        
        $this->info("备份已保存到: storage/app/{$fullPath}");
        
        // 显示备份统计
        try {
            $info = $app->getBackupInfo($backup);
            $this->newLine();
            $this->info('备份统计:');
            $this->table(
                ['对象', '名称', '记录数'],
                array_map(fn($obj) => [
                    class_basename($obj['class']),
                    $obj['name'],
                    $obj['count']
                ], $info['objects'])
            );
            
            $total = array_sum(array_column($info['objects'], 'count'));
            $this->info("总计: {$total} 条记录");
        } catch (\Exception $e) {
            // 忽略统计错误
        }
        
        return 0;
    }

    /**
     * 导入备份
     */
    protected function importBackup(LunaApplication $app): int
    {
        $filename = $this->option('file');
        if (!$filename) {
            $this->error('请使用 --file 选项指定要导入的备份文件');
            return 1;
        }
        
        $path = rtrim($this->option('path') ?: 'luna-backups', '/');
        $fullPath = "{$path}/{$filename}";
        
        if (!Storage::exists($fullPath)) {
            $this->error("备份文件不存在: storage/app/{$fullPath}");
            return 1;
        }
        
        $this->info("正在从 {$fullPath} 导入备份...");
        
        // 读取备份数据
        $backup = Storage::get($fullPath);
        
        // 准备导入选项
        $options = [];
        if ($this->option('force')) {
            $options['force'] = true;
            $this->warn('强制导入模式：将忽略版本检查');
        }
        
        if ($objects = $this->option('objects')) {
            $options['objects'] = $objects;
            $this->info('仅导入: ' . implode(', ', $objects));
        }
        
        // 确认操作
        if (!$this->confirm('确定要导入备份吗？这将覆盖现有数据')) {
            $this->info('操作已取消');
            return 0;
        }
        
        // 执行导入
        $result = $app->importBackup($backup, $options);
        
        // 显示结果
        $this->newLine();
        $this->info('导入完成！');
        
        if (!empty($result['success'])) {
            $this->info('成功导入:');
            foreach ($result['success'] as $item) {
                $this->line(" ✓ {$item['name']} ({$item['count']} 条记录)");
            }
        }
        
        if (!empty($result['skipped'])) {
            $this->warn('跳过的对象:');
            foreach ($result['skipped'] as $class) {
                $this->line(" - " . class_basename($class));
            }
        }
        
        if (!empty($result['failed'])) {
            $this->error('失败的对象:');
            foreach ($result['failed'] as $class => $error) {
                $this->line(" ✗ " . class_basename($class) . ": {$error}");
            }
        }
        
        $successCount = count($result['success']);
        $failedCount = count($result['failed']);
        $skippedCount = count($result['skipped']);
        
        $this->newLine();
        $this->info("总结: 成功 {$successCount}, 失败 {$failedCount}, 跳过 {$skippedCount}");
        
        return $failedCount > 0 ? 1 : 0;
    }

    /**
     * 显示备份信息
     */
    protected function showBackupInfo(LunaApplication $app): int
    {
        $filename = $this->option('file');
        if (!$filename) {
            // 列出所有备份文件
            return $this->listBackupFiles();
        }
        
        $path = rtrim($this->option('path') ?: 'luna-backups', '/');
        $fullPath = "{$path}/{$filename}";
        
        if (!Storage::exists($fullPath)) {
            $this->error("备份文件不存在: storage/app/{$fullPath}");
            return 1;
        }
        
        $this->info("备份文件信息: {$filename}");
        $this->newLine();
        
        // 读取备份数据
        $backup = Storage::get($fullPath);
        
        try {
            $info = $app->getBackupInfo($backup);
            
            // 显示基本信息
            $this->info('基本信息:');
            $this->line("版本: {$info['version']}");
            $this->line("创建时间: {$info['created_at']}");
            $this->line("应用名称: {$info['app_name']}");
            $this->line("环境: {$info['app_env']}");
            
            // 显示对象列表
            $this->newLine();
            $this->info('包含的对象:');
            $this->table(
                ['#', '类名', '名称', '记录数'],
                array_map(fn($obj, $idx) => [
                    $idx + 1,
                    class_basename($obj['class']),
                    $obj['name'],
                    $obj['count']
                ], $info['objects'], array_keys($info['objects']))
            );
            
            $total = array_sum(array_column($info['objects'], 'count'));
            $this->info("总计: " . count($info['objects']) . " 个对象, {$total} 条记录");
            
            // 显示文件信息
            $this->newLine();
            $this->info('文件信息:');
            $size = Storage::size($fullPath);
            $this->line("文件大小: " . $this->formatBytes($size));
            $this->line("修改时间: " . date('Y-m-d H:i:s', Storage::lastModified($fullPath)));
            
        } catch (\Exception $e) {
            $this->error('无法解析备份文件: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * 列出所有备份文件
     */
    protected function listBackupFiles(): int
    {
        $path = $this->option('path') ?: 'luna-backups';
        
        if (!Storage::exists($path)) {
            $this->info('没有找到备份文件');
            return 0;
        }
        
        $files = Storage::files($path);
        $files = array_filter($files, fn($file) => str_ends_with($file, '.dat'));
        
        if (empty($files)) {
            $this->info('没有找到备份文件');
            return 0;
        }
        
        $this->info("备份文件列表 (storage/app/{$path}):");
        $this->newLine();
        
        $fileData = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $size = Storage::size($file);
            $modified = Storage::lastModified($file);
            
            $fileData[] = [
                $filename,
                $this->formatBytes($size),
                date('Y-m-d H:i:s', $modified),
            ];
        }
        
        // 按修改时间排序
        usort($fileData, fn($a, $b) => strcmp($b[2], $a[2]));
        
        $this->table(['文件名', '大小', '修改时间'], $fileData);
        
        $this->newLine();
        $this->info('提示: 使用 --file=<filename> 查看具体备份文件的详细信息');
        
        return 0;
    }

    /**
     * 格式化字节大小
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}