<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Illuminate\Contracts\Support\Arrayable;

class Repository implements Arrayable
{
    /**
     * @var array
     */
    protected array $config = [];

    /**
     * 隐藏的配置项的完整 key
     *
     * @var array
     */
    protected(set) array $hidden = [];

    /**
     * @var bool
     */
    protected(set) bool $isDirty = false;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function set($key, $value, bool $overwrite = true): static
    {
        data_set($this->config, $key, $value, $overwrite);

        // 标记为脏
        $this->isDirty = true;

        return $this;
    }

    public function get($key, $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function all(): array
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

    public function toArray(): array
    {
        return $this->all();
    }
}