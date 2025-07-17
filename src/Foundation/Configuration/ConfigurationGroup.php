<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Random\RandomException;
use Throwable;

class ConfigurationGroup
{
    protected ?Cache $cache = null;

    /**
     * @var Repository[]
     */
    protected array $repositories = [];

    public function __construct(
        protected LunaConfigurationConfigure $configure,
        protected string $group
    ) {
    }

    public function withCache(Cache $cache): static
    {
        $this->cache = $cache;
        return $this;
    }

    public function getConfigurationRecord(string $name): ?Configuration
    {
        return $this->configure->model::query()
            ->with(['current'])
            ->where('group_id', hash_code($this->group))
            ->where('id', hash_code($name))
            ->first();
    }

    /**
     * @throws RandomException
     */
    public function exists(string $name): bool
    {
        try {
            $this->repository($name);
            return true;
        } catch (LunaException $exception) {
            return false;
        }
    }

    /**
     * @throws RandomException
     * @throws Throwable
     */
    public function create(
        string $name,
        string $displayName,
        array $initialValues = [],
        string $description = ''
    ): Repository {
        if (str_contains($name, '.')) {
            throw new LunaException('Configuration name can not contains dot');
        }

        Model::unguarded(function () use ($name, $displayName, $initialValues, $description) {
            $configuration = $this->configure->model::query()->firstOrCreate(
                [
                    'name' => $name,
                    'group_id' => hash_code($this->group),
                ],
                [
                    'display_name' => $displayName,
                    'description' => $description,
                ]
            );

            $configuration->createVersionValue([
                'value' => $initialValues,
            ]);
        });

        return $this->repository($name);
    }

    /**
     * @throws RandomException
     */
    public function repository(string $name): Repository
    {
        if (isset($this->repositories[$name])) {
            return $this->repositories[$name];
        }

        try {
            if ($this->cache) {
                $configuration = $this->cache->remember(
                    sprintf('config:%s:%s', $this->group, $name),
                    random_int(60, 120),
                    function () use ($name) {
                        $configuration = $this->getConfigurationRecord($name);

                        if (!$configuration) {
                            throw new LunaException('Configuration not exists');
                        }

                        return $configuration->toArray();
                    }
                );
            } else {
                $configuration = $this->getConfigurationRecord($name);

                if (!$configuration) {
                    throw new LunaException('Configuration not exists');
                }

                $configuration = $configuration->toArray();
            }

            if (isset($configuration['current'])) {
                $bind = $this->configure->repositoryBinds[$this->group][$name] ?? $this->configure->defaultRepository;
                return $this->repositories[$name] = new $bind($configuration['current']['value']);
            }

            // 配置项不存在
            throw new LunaException('Configuration not exists');
        } catch (RandomException $e) {
            throw new LunaException('Configuration cache error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function target(string $key): array
    {
        if (str_contains($key, '.')) {
            [$name, $keys] = explode('.', $key);
        } else {
            $name = $key;
            $keys = null;
        }

        return [$name, $keys];
    }

    /**
     * @throws RandomException
     */
    public function get(string $key, $default = null, array $hidden = []): mixed
    {
        [$name, $keys] = $this->target($key);

        $repository = $this->repository($name);
        $originHidden = $repository->hidden;
        $value = $this->repository($name)->setHidden($hidden)->get($keys, $default);
        $repository->setHidden($originHidden);

        return $value;
    }

    /**
     * @throws RandomException
     */
    public function set(string $key, $value, bool $overwrite = true): static
    {
        [$name, $keys] = $this->target($key);

        $this->repository($name)->set($keys, $value, $overwrite);

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function save(): void
    {
        foreach ($this->repositories as $name => $repository) {
            if ($repository->isDirty) {
                // 获取原始值
                $originHidden = $repository->hidden;

                // 创建版本数据
                $this->getConfigurationRecord($name)->createVersionValue([
                    'value' => $repository->setHidden([])->all(),
                ]);

                // 恢复原始值
                $repository->setHidden($originHidden);

                // 清理缓存
                $this->cache?->forget(sprintf('config:%s:%s', $this->group, $name));
            }
        }
    }
}