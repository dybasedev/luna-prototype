<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

class LunaHoldingObjectConfigure extends LunaModuleConfigure
{
    /**
     * @var (array{string, class-string<UniqueObject>} | UniqueObject)[]
     */
    protected(set) array $registeredUniqueObjects = [];

    /**
     * @var string 唯一对象持有模型
     */
    public protected(set) string $uniqueObjectHoldingModel = Models\UniqueObjectHolding::class;

    /**
     * @var string 唯一对象持有变动日志模型
     */
    public protected(set) string $uniqueObjectHoldingChangeLogModel = Models\UniqueObjectHoldingChangeLog::class;

    /**
     * @var bool 是否启用数据库唯一索引冲突处理
     */
    public protected(set) bool $useDbUniqueConflictHandling = true;

    /**
     * @var bool 是否启用变动日志
     */
    public protected(set) bool $enableChangeLog = true;

    /**
     * @var HoldingStatus 默认持有状态
     */
    public protected(set) HoldingStatus $defaultStatus = HoldingStatus::Normal;

    /**
     * @var bool 是否使用缓存原子锁
     */
    public protected(set) bool $useCacheLock = true;

    /**
     * @var int 原子锁超时时间（秒）
     */
    public protected(set) int $lockTimeout = 10;

    /**
     * @var int 原子锁等待时间（秒）
     */
    public protected(set) int $lockWaitTimeout = 5;

    /**
     * @var bool 是否启用存在性缓存
     */
    public protected(set) bool $enableExistenceCache = true;

    /**
     * @var int 存在性缓存有效期（秒）
     */
    public protected(set) int $existenceCacheTTL = 300; // 5分钟

    public function name(): string
    {
        return 'luna.holding-object';
    }

    /**
     * @param string $name
     * @param class-string<UniqueObject>|UniqueObject $object
     * @return $this
     */
    public function registerUniqueObject(string $name, string|UniqueObject $object): static
    {
        if (is_string($object)) {
            if (!class_exists($object)) {
                throw LunaException::create("Unique object [$object] dose not exists.");
            }

            $object = [$name, $object];
        } else {
            $object = $object->named($name);
        }

        $this->registeredUniqueObjects[hash_code($name)] = $object;
        return $this;
    }

    /**
     * 获取服务提供者类名
     *
     * @return string|null
     */
    public function serviceProvider(): ?string
    {
        return LunaHoldingObjectServiceProvider::class;
    }

    /**
     * 注册服务到容器
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.holding-object', function ($app) {
            return new LunaHoldingObject(
                $app->make(LunaHoldingObjectConfigure::class),
                $app->make('cache.store')
            );
        });

        $container->alias('luna.holding-object', LunaHoldingObject::class);
    }

    /**
     * 启动配置
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // 注册 BusinessEvent 分组
        $container->make(LunaBusinessEventConfigure::class)->group('holding', '持有对象事件');
    }

    /**
     * 设置唯一对象持有模型
     *
     * @param string $model
     * @return static
     */
    public function setUniqueObjectHoldingModel(string $model): static
    {
        $this->uniqueObjectHoldingModel = $model;
        return $this;
    }

    /**
     * 设置唯一对象持有变动日志模型
     *
     * @param string $model
     * @return static
     */
    public function setUniqueObjectHoldingChangeLogModel(string $model): static
    {
        $this->uniqueObjectHoldingChangeLogModel = $model;
        return $this;
    }

    /**
     * 设置是否启用数据库唯一索引冲突处理
     *
     * @param bool $enable
     * @return static
     */
    public function setUseDbUniqueConflictHandling(bool $enable): static
    {
        $this->useDbUniqueConflictHandling = $enable;
        return $this;
    }

    /**
     * 设置是否启用变动日志
     *
     * @param bool $enable
     * @return static
     */
    public function setEnableChangeLog(bool $enable): static
    {
        $this->enableChangeLog = $enable;
        return $this;
    }

    /**
     * 设置默认持有状态
     *
     * @param HoldingStatus $status
     * @return static
     */
    public function setDefaultStatus(HoldingStatus $status): static
    {
        $this->defaultStatus = $status;
        return $this;
    }

    /**
     * 设置是否使用缓存原子锁
     *
     * @param bool $use
     * @return static
     */
    public function setUseCacheLock(bool $use): static
    {
        $this->useCacheLock = $use;
        return $this;
    }

    /**
     * 设置原子锁超时时间
     *
     * @param int $seconds
     * @return static
     */
    public function setLockTimeout(int $seconds): static
    {
        $this->lockTimeout = $seconds;
        return $this;
    }

    /**
     * 设置原子锁等待时间
     *
     * @param int $seconds
     * @return static
     */
    public function setLockWaitTimeout(int $seconds): static
    {
        $this->lockWaitTimeout = $seconds;
        return $this;
    }

    /**
     * 设置是否启用存在性缓存
     *
     * @param bool $enable
     * @return static
     */
    public function setEnableExistenceCache(bool $enable): static
    {
        $this->enableExistenceCache = $enable;
        return $this;
    }

    /**
     * 设置存在性缓存有效期
     *
     * @param int $seconds
     * @return static
     */
    public function setExistenceCacheTTL(int $seconds): static
    {
        $this->existenceCacheTTL = $seconds;
        return $this;
    }

}