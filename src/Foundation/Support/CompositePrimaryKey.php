<?php

namespace Dybasedev\LunaPrototype\Foundation\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * 复合主键支持
 * 
 * Laravel 不原生支持复合主键，此 trait 提供基本支持
 */
trait CompositePrimaryKey
{
    /**
     * 获取主键值
     *
     * @return array
     */
    public function getKey(): array
    {
        $keys = [];
        foreach ($this->primaryKey as $key) {
            $keys[$key] = $this->getAttribute($key);
        }
        return $keys;
    }

    /**
     * 设置主键范围
     *
     * @param Builder $query
     * @return Builder
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        foreach ($this->primaryKey as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }
        return $query;
    }

    /**
     * 获取查询键
     *
     * @return array
     */
    public function getKeyForSaveQuery(): array
    {
        $keys = [];
        foreach ($this->primaryKey as $key) {
            $keys[$key] = $this->getAttribute($key);
        }
        return $keys;
    }
}