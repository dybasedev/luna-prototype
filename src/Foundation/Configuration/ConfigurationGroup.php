<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration;

use Dybasedev\LunaPrototype\Foundation\Configuration\Models\Configuration;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Random\RandomException;
use Throwable;

/**
 * 配置组管理类
 *
 * 负责管理特定配置组下的所有配置项，提供配置的创建、读取、更新、版本管理等功能。
 * 支持缓存机制以提高性能，支持配置版本控制和切换。
 *
 * @package Dybasedev\LunaPrototype\Foundation\Configuration
 */
class ConfigurationGroup
{
    /**
     * 缓存实例
     *
     * @var Cache|null
     */
    protected ?Cache $cache = null;

    /**
     * 配置仓库实例集合
     *
     * @var Repository[]
     */
    protected array $repositories = [];

    /**
     * 构造函数
     *
     * @param LunaConfigurationConfigure $configure 配置管理器
     * @param string $group 配置组名称
     */
    public function __construct(
        protected LunaConfigurationConfigure $configure,
        protected string $group
    ) {
    }

    /**
     * 设置缓存实例
     *
     * @param Cache $cache 缓存实例
     * @return static
     */
    public function withCache(Cache $cache): static
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * 获取配置记录模型
     *
     * @param string $name 配置名称
     * @return Configuration|null 配置模型实例，不存在时返回 null
     */
    public function getConfigurationRecord(string $name): ?Configuration
    {
        return $this->configure->model::query()
            ->with(['current'])
            ->where('group_id', hash_code($this->group))
            ->where('id', hash_code($name))
            ->first();
    }

    /**
     * 检查配置是否存在
     *
     * @param string $name 配置名称
     * @return bool 配置是否存在
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
     * 创建新的配置项
     *
     * @param string $name 配置名称（不能包含点号）
     * @param string $displayName 配置显示名称
     * @param array $initialValues 初始值
     * @param string $description 配置描述
     * @return Repository 配置仓库实例
     * @throws RandomException 随机数生成异常
     * @throws Throwable 其他异常
     * @throws LunaException
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
     * 获取配置仓库实例
     *
     * 如果仓库实例已存在则直接返回，否则从数据库或缓存中加载配置并创建仓库实例。
     *
     * @param string $name 配置名称
     * @return Repository 配置仓库实例
     * @throws LunaException 配置不存在或缓存错误时抛出
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

    /**
     * 解析配置键
     *
     * 将点分隔的配置键解析为配置名称和子键。
     *
     * @param string $key 配置键（如 'database.host'）
     * @return array [配置名称, 子键]
     */
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
     * 获取配置值
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @param array $hidden 临时隐藏的字段
     * @return mixed 配置值
     */
    public function get(string $key, mixed $default = null, array $hidden = []): mixed
    {
        [$name, $keys] = $this->target($key);

        $repository = $this->repository($name);
        $originHidden = $repository->hidden;
        $value = $repository->setHidden($hidden)->get($keys, $default);
        $repository->setHidden($originHidden);

        return $value;
    }

    /**
     * 设置配置值
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @param bool $overwrite 是否覆盖已存在的值
     * @return static
     */
    public function set(string $key, $value, bool $overwrite = true): static
    {
        [$name, $keys] = $this->target($key);

        $this->repository($name)->set($keys, $value, $overwrite);

        return $this;
    }

    /**
     * 保存所有已修改的配置
     *
     * 将所有标记为脏数据的配置仓库保存到数据库，并清理相关缓存。
     *
     * @return void
     * @throws Throwable 保存失败时抛出
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