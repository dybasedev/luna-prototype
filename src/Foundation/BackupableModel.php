<?php

namespace Dybasedev\LunaPrototype\Foundation;

use ArrayIterator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Iterator;

/**
 * 可备份模型 Trait
 * 
 * 为 Eloquent 模型提供备份和恢复功能的默认实现。
 * 使用此 trait 的模型可以轻松实现数据的导出和导入。
 * 
 * 使用要求：
 * - 模型必须继承自 Illuminate\Database\Eloquent\Model
 * - 模型必须实现 Backupable 接口
 * - 建议与 NamedId trait 配合使用
 * 
 * @mixin Model
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
trait BackupableModel
{
    /**
     * 获取备份对象的关联键配置
     * 
     * 默认实现：
     * - 如果使用了 NamedId trait，返回 'name'
     * - 否则返回 null（使用主键）
     * 
     * 可以在模型中重写此方法以自定义关联键
     * 
     * @return string|array|null
     */
    public static function getBackupableRelationKey(): string|array|null
    {
        // 检查是否使用了 NamedId trait
        if (in_array(NamedId::class, class_uses_recursive(static::class))) {
            return 'name';
        }
        
        return null;
    }

    /**
     * 获取备份对象的名称标识
     * 
     * 默认使用模型的表名作为备份名称
     * 
     * @return string
     */
    public static function getBackupableName(): string
    {
        return (new static)->getTable();
    }

    /**
     * 备份数据迭代器
     * 
     * 默认实现会返回模型的所有数据。
     * 使用分块查询以处理大数据量的情况。
     * 
     * @return Iterator<array>
     */
    public static function backupDatasourceIterator(): Iterator
    {
        $query = static::query();
        
        // 获取所有需要备份的字段
        $model = new static;
        $columns = $model->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($model->getTable());
        
        // 收集所有数据
        $data = [];
        
        // 使用分块查询以节省内存
        $query->chunk(1000, function ($records) use (&$data, $columns) {
            foreach ($records as $record) {
                // 只导出存在的字段
                $recordData = [];
                foreach ($columns as $column) {
                    if (isset($record->$column)) {
                        $recordData[$column] = $record->$column;
                    }
                }
                
                // 处理 JSON 字段
                foreach ($record->getCasts() as $key => $cast) {
                    if (in_array($cast, ['array', 'json', 'object', 'collection']) && isset($recordData[$key])) {
                        // 确保 JSON 数据正确序列化
                        $recordData[$key] = $record->getAttributeValue($key);
                    }
                }
                
                $data[] = $recordData;
            }
        });
        
        return new ArrayIterator($data);
    }

    /**
     * 恢复数据
     * 
     * 从备份迭代器中恢复数据。
     * 使用事务确保数据一致性，支持更新或创建。
     * 
     * @param Iterator $backup 备份数据迭代器
     * @return void
     * @throws \Exception
     */
    public static function recoverFromBackupIterator(Iterator $backup): void
    {
        DB::transaction(function () use ($backup) {
            $relationKey = static::getBackupableRelationKey();
            
            foreach ($backup as $data) {
                // 准备数据，移除不应该直接设置的字段
                $attributes = $data;
                unset($attributes['created_at'], $attributes['updated_at']);
                
                if ($relationKey === null) {
                    // 使用主键
                    $keyName = (new static)->getKeyName();
                    if (isset($data[$keyName])) {
                        static::updateOrCreate(
                            [$keyName => $data[$keyName]],
                            $attributes
                        );
                    } else {
                        static::create($attributes);
                    }
                } elseif (is_string($relationKey)) {
                    // 单一关联键
                    if (isset($data[$relationKey])) {
                        static::updateOrCreate(
                            [$relationKey => $data[$relationKey]],
                            $attributes
                        );
                    } else {
                        throw new \RuntimeException("Relation key '{$relationKey}' not found in backup data");
                    }
                } elseif (is_array($relationKey)) {
                    // 复合关联键
                    $conditions = [];
                    foreach ($relationKey as $key) {
                        if (!isset($data[$key])) {
                            throw new \RuntimeException("Relation key '{$key}' not found in backup data");
                        }
                        $conditions[$key] = $data[$key];
                    }
                    static::updateOrCreate($conditions, $attributes);
                }
            }
        });
    }

    /**
     * 获取备份数据的依赖关系
     * 
     * 默认返回空数组，表示没有依赖。
     * 可以在模型中重写此方法以定义依赖关系。
     * 
     * @return array<class-string<Backupable>>
     */
    public static function getBackupableDependencies(): array
    {
        return [];
    }

    /**
     * 获取需要在备份中排除的字段
     * 
     * 可以在模型中重写此方法以排除敏感字段
     * 
     * @return array
     */
    protected static function getBackupableExcludedFields(): array
    {
        return [];
    }

    /**
     * 在备份前处理数据
     * 
     * 可以在模型中重写此方法以自定义数据处理逻辑
     * 
     * @param array $data
     * @return array
     */
    protected static function processBackupData(array $data): array
    {
        return $data;
    }

    /**
     * 在恢复前处理数据
     * 
     * 可以在模型中重写此方法以自定义数据处理逻辑
     * 
     * @param array $data
     * @return array
     */
    protected static function processRecoverData(array $data): array
    {
        return $data;
    }
}