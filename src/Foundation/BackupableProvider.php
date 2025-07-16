<?php

namespace Dybasedev\LunaPrototype\Foundation;

/**
 * 可备份对象提供者
 */
interface BackupableProvider
{
    /**
     * 获取所有可备份对象
     *
     * @return class-string<Backupable>[]
     */
    public function backupableObjects(): array;
}