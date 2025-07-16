<?php

namespace Dybasedev\LunaPrototype\Foundation;

use DirectoryIterator;

class BackupableDirectoryProvider implements BackupableProvider
{

    public function __construct(protected(set) string $path)
    {
    }

    public static function path(string $path): static
    {
        return new static($path);
    }

    public function backupableObjects(): array
    {
        $dir = new DirectoryIterator($this->path);
        $backupableObjects = [];

        // TODO
    }


}