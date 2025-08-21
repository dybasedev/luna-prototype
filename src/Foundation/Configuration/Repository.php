<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Illuminate\Contracts\Support\Arrayable;

/**
 * 配置仓库类
 *
 * 提供配置数据的存储和访问功能，支持点语法访问嵌套配置。
 * 支持脏数据检测和字段隐藏功能。
 *
 * @package Dybasedev\LunaPrototype\Foundation\Configuration
 */
class Repository implements Arrayable
{
    /**
     * 配置数据数组
     *
     * @var array
     */
    protected array $config = [];

    /**
     * 隐藏的配置项的完整 key
     *
     * 在输出配置时，这些键对应的值将被过滤掉。
     *
     * @var array<string>
     */
    protected(set) array $hidden = [];

    /**
     * 脏数据标识
     *
     * 标识配置是否已被修改但尚未保存。
     *
     * @var bool
     */
    protected(set) bool $isDirty = false;

    /**
     * 构造函数
     *
     * @param array $config 初始配置数据
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 设置隐藏字段
     *
     * @param array<string> $hidden 要隐藏的字段键名数组
     * @return static
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * 设置配置值
     *
     * @param string|null $key 配置键，支持点语法。为 null 时替换整个配置数组
     * @param mixed $value 配置值
     * @param bool $overwrite 是否覆盖已存在的值
     * @return static
     */
    public function set(string|null $key, mixed $value, bool $overwrite = true): static
    {
        if ($key) {
            data_set($this->config, $key, $value, $overwrite);
        } else {
            if ($overwrite) {
                $this->config = $value;
            }
        }

        // 标记为脏
        $this->isDirty = true;

        return $this;
    }

    /**
     * 获取配置值
     *
     * @param string|null $key 配置键，支持点语法。为 null 时返回所有配置
     * @param mixed $default 默认值，当配置不存在时返回
     * @return mixed 配置值
     */
    public function get(string|null $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    /**
     * 检查配置项是否存在
     *
     * @param string $key 配置键，支持点语法
     * @return bool 配置项是否存在
     */
    public function has(string $key): bool
    {
        return data_get($this->all(), $key) !== null;
    }

    /**
     * 获取所有配置数据
     *
     * @return array 完整的配置数组（不受隐藏字段影响）
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * 从另一个仓库创建新实例
     *
     * @param Repository $repository 源仓库
     * @return static 新的仓库实例
     */
    public static function fromRepository(Repository $repository): static
    {
        return new static($repository->all());
    }

    /**
     * @deprecated 请使用 all() 方法代替
     * @return array
     */
    #[\Deprecated]
    public function everything(): array
    {
        return $this->config;
    }

    /**
     * 转换为数组
     *
     * 返回配置数组，已设置的隐藏字段将被过滤掉。
     *
     * @return array 过滤后的配置数组
     */
    public function toArray(): array
    {
        if ($this->hidden) {
            $config = $this->config;
            foreach ($this->hidden as $key) {
                data_forget($config, $key);
            }

            return $config;
        }

        return $this->config;
    }
}