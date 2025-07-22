<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Iterator;

/**
 * 可备份对象接口
 * 
 * 定义了数据备份和恢复的标准契约。
 * 实现此接口的类可以将其数据导出为可传输的格式，
 * 并能从备份数据中恢复。
 * 
 * 主要用途：
 * - 配置数据的迁移
 * - 业务依赖数据的同步
 * - 开发测试环境的数据准备
 * - 生产环境的数据备份
 * 
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface Backupable
{
    /**
     * 获取备份对象的关联键配置
     * 
     * 返回用于标识和关联数据的键名。
     * 对于有 NamedId trait 的模型，默认使用 'name' 作为关联键。
     * 对于自增主键的模型，可以使用 'code'、'slug' 等唯一字段。
     * 
     * 返回格式：
     * - string: 单一关联键，如 'name', 'code'
     * - array: 复合关联键，如 ['type', 'code']
     * - null: 使用主键（适用于 NamedId 模型）
     * 
     * @return string|array|null 关联键配置
     */
    public static function getBackupableRelationKey(): string|array|null;

    /**
     * 获取备份对象的名称标识
     * 
     * 返回一个唯一的名称标识，用于在备份数据中标识此类对象。
     * 通常使用类名的简短形式或业务名称。
     * 
     * @return string 备份对象名称
     */
    public static function getBackupableName(): string;

    /**
     * 备份数据迭代器
     * 
     * 返回一个迭代器，用于遍历所有需要备份的数据。
     * 每次迭代应该返回一个数组，包含单条记录的所有数据。
     * 
     * 注意事项：
     * - 应该包含所有必要的关联数据
     * - 敏感数据应该进行适当处理
     * - 大数据量时应该考虑分批处理
     * 
     * @return Iterator<array> 数据迭代器
     */
    public static function backupDatasourceIterator(): Iterator;

    /**
     * 恢复数据
     * 
     * 从备份迭代器中恢复数据。
     * 实现时应该处理数据冲突、关联关系等问题。
     * 
     * 注意事项：
     * - 应该使用事务保证数据一致性
     * - 处理主键冲突（updateOrCreate）
     * - 维护正确的关联关系
     * - 记录恢复日志
     * 
     * @param Iterator $backup 备份数据迭代器
     * @return void
     * @throws \Exception 恢复失败时抛出异常
     */
    public static function recoverFromBackupIterator(Iterator $backup): void;

    /**
     * 获取备份数据的依赖关系
     * 
     * 返回此备份对象依赖的其他备份对象类名。
     * 用于确定恢复顺序，被依赖的对象应该先恢复。
     * 
     * @return array<class-string<Backupable>> 依赖的备份对象类名列表
     */
    public static function getBackupableDependencies(): array;
}