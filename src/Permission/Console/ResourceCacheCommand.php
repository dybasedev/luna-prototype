<?php

namespace Dybasedev\LunaPrototype\Permission\Console;

use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Illuminate\Console\Command;

/**
 * 权限资源缓存管理命令
 */
class ResourceCacheCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'luna:permission:resources {action=list : 操作类型 list|refresh|clear}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '管理权限资源缓存';

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listResources(),
            'refresh' => $this->refreshCache(),
            'clear' => $this->clearCache(),
            default => $this->invalidAction(),
        };
    }

    /**
     * 列出所有资源
     *
     * @return int
     */
    protected function listResources(): int
    {
        $configure = app(LunaPermissionConfigure::class);
        $registry = $configure->getResourceRegistry();
        
        $this->info('已注册的权限资源：');
        
        $resources = [];
        foreach ($registry->all() as $name => $definition) {
            $resources[] = [
                'name' => $name,
                'description' => $definition['description'] ?? '-',
                'actions' => is_array($definition['actions'] ?? null) 
                    ? implode(', ', $definition['actions']) 
                    : '-',
                'from' => isset($definition['class']) ? 'Attribute' : 'Manual',
            ];
        }
        
        if (empty($resources)) {
            $this->warn('没有找到任何资源定义');
            return 0;
        }
        
        $this->table(
            ['资源名称', '描述', '操作', '来源'],
            $resources
        );
        
        $this->info('总计: ' . count($resources) . ' 个资源');
        
        return 0;
    }

    /**
     * 刷新缓存
     *
     * @return int
     */
    protected function refreshCache(): int
    {
        $configure = app(LunaPermissionConfigure::class);
        $provider = $configure->resourceProvider;
        
        if (!$provider) {
            $this->warn('未配置资源提供者，无需刷新缓存');
            return 0;
        }
        
        $this->info('正在刷新资源缓存...');
        
        // 强制刷新
        $resources = $provider->getResources(true);
        
        $this->info('✓ 缓存已刷新');
        $this->info('  发现 ' . count($resources) . ' 个资源');
        
        return 0;
    }

    /**
     * 清除缓存
     *
     * @return int
     */
    protected function clearCache(): int
    {
        $configure = app(LunaPermissionConfigure::class);
        $provider = $configure->resourceProvider;
        
        if (!$provider) {
            $this->warn('未配置资源提供者，无需清除缓存');
            return 0;
        }
        
        $provider->clearCache();
        $this->info('✓ 资源缓存已清除');
        
        return 0;
    }

    /**
     * 无效操作
     *
     * @return int
     */
    protected function invalidAction(): int
    {
        $this->error('无效的操作类型');
        $this->info('可用的操作: list, refresh, clear');
        
        return 1;
    }
}