<?php

namespace Dybasedev\LunaPrototype\Foundation;

use ArrayIterator;
use Iterator;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;

/**
 * Luna 应用程序主类
 *
 * 这是 Luna 原型框架的核心应用程序类，继承自 LunaModule。
 * 它作为整个框架的入口点，负责协调各个模块的工作。
 *
 * 主要功能：
 * - 管理应用程序的配置
 * - 协调各个模块的初始化
 * - 提供统一的应用程序接口
 * - 处理应用程序级别的业务逻辑
 * - 管理数据备份和恢复
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaApplication extends LunaModule
{
    /**
     * 备份数据版本号
     */
    const BACKUP_VERSION = '1.0';

    /**
     * 创建 Luna 应用程序实例
     *
     * @param LunaApplicationConfigure $configure 应用程序配置对象
     */
    public function __construct(
        protected(set) LunaApplicationConfigure $configure,
    )
    {
    }

    /**
     * 导出备份数据
     * 
     * 将所有已注册的可备份对象的数据导出为序列化格式。
     * 支持选择性导出和数据压缩。
     * 
     * @param array $options 导出选项
     *   - 'objects' => array 要导出的对象类名列表（为空则导出全部）
     *   - 'compress' => bool 是否压缩数据（默认 true）
     *   - 'base64' => bool 是否进行 base64 编码（默认 true）
     * @return string 导出的数据
     * @throws LunaException
     */
    public function exportBackup(array $options = []): string
    {
        $objects = $options['objects'] ?? [];
        $compress = $options['compress'] ?? true;
        $base64 = $options['base64'] ?? true;

        // 获取所有可备份对象
        $backupableObjects = $this->getBackupableObjects();
        
        // 如果指定了对象列表，则过滤
        if (!empty($objects)) {
            $backupableObjects = array_intersect($backupableObjects, $objects);
        }

        // 按依赖关系排序
        $sortedObjects = $this->sortBackupablesByDependencies($backupableObjects);

        // 收集备份数据
        $backupData = [
            'version' => self::BACKUP_VERSION,
            'created_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'objects' => [],
        ];

        foreach ($sortedObjects as $className) {
            try {
                /** @var Backupable $className */
                $iterator = $className::backupDatasourceIterator();
                $data = [];
                
                foreach ($iterator as $item) {
                    $data[] = $item;
                }

                $backupData['objects'][] = [
                    'class' => $className,
                    'name' => $className::getBackupableName(),
                    'relation_key' => $className::getBackupableRelationKey(),
                    'count' => count($data),
                    'data' => $data,
                ];
            } catch (\Throwable $e) {
                throw LunaException::create($e)
                    ->withDisplayMessage("导出 {$className} 的备份数据时发生错误")
                    ->withData(['class' => $className, 'error' => $e->getMessage()])
                    ->withHttpStatus(500);
            }
        }

        // 序列化数据
        $serialized = serialize($backupData);

        // 压缩数据
        if ($compress) {
            $serialized = gzcompress($serialized, 9);
        }

        // Base64 编码
        if ($base64) {
            $serialized = base64_encode($serialized);
        }

        return $serialized;
    }

    /**
     * 导入备份数据
     * 
     * 从备份数据中恢复对象数据。
     * 支持选择性导入和版本检查。
     * 
     * @param string $data 备份数据
     * @param array $options 导入选项
     *   - 'objects' => array 要导入的对象类名列表（为空则导入全部）
     *   - 'force' => bool 是否强制导入（忽略版本检查）
     *   - 'compressed' => bool 数据是否已压缩（默认自动检测）
     *   - 'base64' => bool 数据是否已 base64 编码（默认自动检测）
     * @return array 导入结果
     * @throws LunaException
     */
    public function importBackup(string $data, array $options = []): array
    {
        $objects = $options['objects'] ?? [];
        $force = $options['force'] ?? false;
        $compressed = $options['compressed'] ?? null;
        $base64 = $options['base64'] ?? null;

        // 自动检测 base64 编码
        if ($base64 === null) {
            // 严格模式的 base64 检测
            $decoded = base64_decode($data, true);
            $base64 = $decoded !== false && base64_encode($decoded) === $data;
        }

        // Base64 解码
        if ($base64) {
            $data = base64_decode($data);
            if ($data === false) {
                throw LunaException::create('备份数据格式错误')
                    ->withDisplayMessage('无效的 base64 编码数据')
                    ->withHttpStatus(400);
            }
        }

        // 自动检测压缩
        if ($compressed === null) {
            // 检查 gzip 标识
            $compressed = substr($data, 0, 2) === "\x78\x9c" || // zlib
                         substr($data, 0, 2) === "\x78\x01" || // zlib no compression
                         substr($data, 0, 2) === "\x78\xda";   // zlib best compression
        }

        // 解压缩数据
        if ($compressed) {
            $data = gzuncompress($data);
            if ($data === false) {
                throw LunaException::create('备份数据解压失败')
                    ->withDisplayMessage('无法解压缩备份数据，文件可能已损坏')
                    ->withHttpStatus(400);
            }
        }

        // 反序列化数据
        try {
            $backupData = @unserialize($data);
            if ($backupData === false) {
                throw new \Exception('Unserialize failed');
            }
        } catch (\Throwable $e) {
            throw LunaException::create($e, 0, false)
                ->withDisplayMessage('无法解析备份数据，格式不正确')
                ->withData(['error' => $e->getMessage()])
                ->withHttpStatus(400);
        }

        // 版本检查
        if (!$force && $backupData['version'] !== self::BACKUP_VERSION) {
            throw LunaException::create('备份版本不匹配')
                ->withDisplayMessage(sprintf(
                    '备份版本不匹配。期望版本: %s, 实际版本: %s',
                    self::BACKUP_VERSION,
                    $backupData['version']
                ))
                ->withData([
                    'expected' => self::BACKUP_VERSION,
                    'actual' => $backupData['version']
                ])
                ->withHttpStatus(400);
        }

        // 导入结果
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];

        // 按顺序恢复数据
        foreach ($backupData['objects'] as $objectData) {
            $className = $objectData['class'];

            // 检查是否需要导入
            if (!empty($objects) && !in_array($className, $objects)) {
                $results['skipped'][] = $className;
                continue;
            }

            // 检查类是否存在
            if (!class_exists($className)) {
                $results['failed'][$className] = 'Class not found';
                continue;
            }

            // 检查是否实现了 Backupable 接口
            if (!in_array(Backupable::class, class_implements($className))) {
                $results['failed'][$className] = 'Class does not implement Backupable interface';
                continue;
            }

            try {
                /** @var Backupable $className */
                $iterator = new ArrayIterator($objectData['data']);
                $className::recoverFromBackupIterator($iterator);
                
                $results['success'][] = [
                    'class' => $className,
                    'name' => $objectData['name'],
                    'count' => $objectData['count'],
                ];
            } catch (\Throwable $e) {
                $results['failed'][$className] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 获取所有可备份对象
     * 
     * 从配置中收集所有已注册的可备份对象。
     * 
     * @return array<class-string<Backupable>>
     */
    public function getBackupableObjects(): array
    {
        $objects = [];

        // 从直接注册的对象
        $objects = array_merge($objects, $this->configure->backupableObjects);

        // 从提供者收集
        foreach ($this->configure->backupableProviders as $provider) {
            if ($provider instanceof BackupableProvider) {
                $objects = array_merge($objects, $provider->backupableObjects());
            }
        }

        return array_unique($objects);
    }

    /**
     * 按依赖关系排序可备份对象
     * 
     * 使用拓扑排序确保被依赖的对象先被处理。
     * 
     * @param array<class-string<Backupable>> $objects 要排序的对象列表
     * @return array<class-string<Backupable>> 排序后的对象列表
     * @throws LunaException 如果存在循环依赖
     */
    protected function sortBackupablesByDependencies(array $objects): array
    {
        $graph = [];
        $inDegree = [];

        // 初始化图和入度
        foreach ($objects as $object) {
            $graph[$object] = [];
            $inDegree[$object] = 0;
        }

        // 构建依赖图
        foreach ($objects as $object) {
            /** @var Backupable $object */
            $dependencies = $object::getBackupableDependencies();
            
            foreach ($dependencies as $dependency) {
                if (in_array($dependency, $objects)) {
                    $graph[$dependency][] = $object;
                    $inDegree[$object]++;
                }
            }
        }

        // 拓扑排序
        $queue = [];
        $sorted = [];

        // 找出所有入度为 0 的节点
        foreach ($inDegree as $object => $degree) {
            if ($degree === 0) {
                $queue[] = $object;
            }
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            // 处理当前节点的所有邻接节点
            foreach ($graph[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // 检查是否存在循环依赖
        if (count($sorted) !== count($objects)) {
            throw LunaException::create('检测到循环依赖')
                ->withDisplayMessage('备份对象之间存在循环依赖关系')
                ->withData(['objects' => array_keys($inDegree)])
                ->withHttpStatus(500);
        }

        return $sorted;
    }

    /**
     * 获取备份信息
     * 
     * 分析备份数据并返回其信息，不执行实际导入。
     * 
     * @param string $data 备份数据
     * @param array $options 选项（同 importBackup）
     * @return array 备份信息
     * @throws LunaException
     */
    public function getBackupInfo(string $data, array $options = []): array
    {
        $compressed = $options['compressed'] ?? null;
        $base64 = $options['base64'] ?? null;

        // 自动检测 base64 编码
        if ($base64 === null) {
            // 严格模式的 base64 检测
            $decoded = base64_decode($data, true);
            $base64 = $decoded !== false && base64_encode($decoded) === $data;
        }

        // Base64 解码
        if ($base64) {
            $data = base64_decode($data);
            if ($data === false) {
                throw LunaException::create('备份数据格式错误')
                    ->withDisplayMessage('无效的 base64 编码数据')
                    ->withHttpStatus(400);
            }
        }

        // 自动检测压缩
        if ($compressed === null) {
            $compressed = substr($data, 0, 2) === "\x78\x9c" || 
                         substr($data, 0, 2) === "\x78\x01" || 
                         substr($data, 0, 2) === "\x78\xda";
        }

        // 解压缩数据
        if ($compressed) {
            $data = gzuncompress($data);
            if ($data === false) {
                throw LunaException::create('备份数据解压失败')
                    ->withDisplayMessage('无法解压缩备份数据，文件可能已损坏')
                    ->withHttpStatus(400);
            }
        }

        // 反序列化数据
        try {
            $backupData = @unserialize($data);
            if ($backupData === false) {
                throw new \Exception('Unserialize failed');
            }
        } catch (\Throwable $e) {
            throw LunaException::create($e, 0, false)
                ->withDisplayMessage('无法解析备份数据，格式不正确')
                ->withData(['error' => $e->getMessage()])
                ->withHttpStatus(400);
        }

        // 构建信息
        $info = [
            'version' => $backupData['version'],
            'created_at' => $backupData['created_at'],
            'app_name' => $backupData['app_name'],
            'app_env' => $backupData['app_env'],
            'objects' => [],
        ];

        foreach ($backupData['objects'] as $objectData) {
            $info['objects'][] = [
                'class' => $objectData['class'],
                'name' => $objectData['name'],
                'count' => $objectData['count'],
            ];
        }

        return $info;
    }
}