<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Iterator;

/**
 * 可备份对象
 */
interface Backupable
{
    /**
     * 备份数据迭代器
     *
     * @return Iterator
     */
    public static function backupDatasourceIterator(): Iterator;

    /**
     * 恢复数据
     *
     * @param Iterator $backup
     * @return void
     */
    public static function recoverFromBackupIterator(Iterator $backup): void;
}