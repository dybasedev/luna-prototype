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
            [$name, $keys] = explode('.', $key, 2);
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
        $value = $repository->setHidden($hidden)->get($keys, $default);
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
        
        // 清空内存中的仓库，强制下次重新加载
        $this->repositories = [];
    }

    /**
     * 获取指定配置的指定版本
     *
     * @param string $name 配置名称
     * @param string $versionId 版本ID
     * @return Repository|null
     * @throws RandomException
     */
    public function getVersion(string $name, string $versionId): ?Repository
    {
        $configuration = $this->getConfigurationRecord($name);
        
        if (!$configuration) {
            throw new LunaException('Configuration not exists');
        }

        $version = $configuration->versions()
            ->where('version_id', $versionId)
            ->first();
        
        if (!$version) {
            return null;
        }

        $bind = $this->configure->repositoryBinds[$this->group][$name] ?? $this->configure->defaultRepository;
        return new $bind($version->value);
    }

    /**
     * 切换到指定版本
     *
     * @param string $name 配置名称
     * @param string $versionId 版本ID
     * @return bool
     * @throws RandomException
     */
    public function switchVersion(string $name, string $versionId): bool
    {
        $configuration = $this->getConfigurationRecord($name);
        
        if (!$configuration) {
            throw new LunaException('Configuration not exists');
        }

        $result = $configuration->switchTo($versionId, true);
        
        if ($result) {
            // 清理缓存
            $this->cache?->forget(sprintf('config:%s:%s', $this->group, $name));
            
            // 更新内存中的仓库
            unset($this->repositories[$name]);
        }
        
        return $result;
    }

    /**
     * 获取配置的所有版本列表
     *
     * @param string $name 配置名称
     * @return array
     * @throws RandomException
     */
    public function getVersionList(string $name): array
    {
        $configuration = $this->getConfigurationRecord($name);
        
        if (!$configuration) {
            throw new LunaException('Configuration not exists');
        }

        // 刷新配置记录以获取最新的 current_version_id
        $configuration->refresh();

        $versions = $configuration->versions()
            ->orderBy('created_at', 'desc')
            ->orderBy('version_id', 'desc')
            ->get()
            ->map(function ($version) use ($configuration) {
                return [
                    'version_id' => $version->version_id,
                    'is_current' => $version->version_id === $configuration->current_version_id,
                    'created_at' => $version->created_at,
                ];
            })
            ->toArray();
            
        // 如果所有版本的 created_at 相同，则将当前版本移到第一位
        if (count($versions) > 1 && $versions[0]['created_at']->equalTo($versions[count($versions) - 1]['created_at'])) {
            usort($versions, function($a, $b) {
                if ($a['is_current']) return -1;
                if ($b['is_current']) return 1;
                return 0;
            });
        }
        
        return $versions;
    }

    /**
     * 获取当前版本ID
     *
     * @param string $name 配置名称
     * @return string|null
     * @throws RandomException
     */
    public function getCurrentVersionId(string $name): ?string
    {
        $configuration = $this->getConfigurationRecord($name);
        
        if (!$configuration) {
            throw new LunaException('Configuration not exists');
        }

        return $configuration->current_version_id;
    }
}