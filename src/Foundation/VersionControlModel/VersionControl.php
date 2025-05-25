<?php

namespace Dybasedev\LunaPrototype\Foundation\VersionControlModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Throwable;

/**
 * @mixin Model
 */
trait VersionControl
{
    /**
     * 版本数据模型
     *
     * @return string
     */
    abstract public function versionValueModel(): string;

    /**
     * 记录当前版本数据的字段名，例如 current_version_id
     *
     * @return string
     */
    public function currentVersionValueKey(): string
    {
        return 'current_version_id';
    }

    /**
     * 关联本地键，一般是当前表主键
     *
     * @return string
     */
    public function relationLocalKey(): string
    {
        return $this->getKeyName();
    }

    /**
     * 关联版本数据本地键，即在版本数据表中的主键名，记录版本 hash 值
     *
     * @return string
     */
    public function relationVersionValueLocalKey(): string
    {
        return 'version_id';
    }

    /**
     * 关联版本数据外键，即在版本数据表中的外键名
     *
     * 例如当前表是 configurations，版本数据表是 configuration_values，
     * 则 relationVersionValueForeignKey() 返回的一般是 configuration_id
     *
     * @return string
     */
    public function relationVersionValueForeignKey(): string
    {
        return 'index_id';
    }

    /**
     * 当前版本数据
     *
     * @return BelongsTo
     */
    public function current(): BelongsTo
    {
        return $this->belongsTo(
            $this->versionValueModel(),
            $this->currentVersionValueKey(),
            $this->relationVersionValueLocalKey(),
        );
    }

    /**
     * 版本数据
     *
     * @return HasMany
     */
    public function versions(): HasMany
    {
        return $this->hasMany(
            $this->versionValueModel(),
            $this->relationVersionValueForeignKey(),
            $this->relationLocalKey()
        );
    }

    /**
     * 创建新的版本数据
     *
     * @param array $data
     * @return void
     * @throws Throwable
     */
    public function createVersionValue(array $data): void
    {
        $process = function () use ($data) {
            // 对数据进行排序，保证顺序一致
            ksort($data);

            // 生成 hash 值
            $hash = sha1(json_encode(['id' => $this->{$this->relationLocalKey()}, 'hash' => sha1(json_encode($data))]));

            try {
                /** @var Model $model */
                $model = new ($this->versionValueModel());
                $model->{$this->relationVersionValueLocalKey()} = $hash;
                foreach ($data as $key => $value) {
                    $model->{$key} = $value;
                }

                $model->{$this->relationVersionValueForeignKey()} = $this->{$this->relationLocalKey()};
                $model->save();
            } catch (QueryException $exception) {
                // 判断是否重复主键，若是重复则无视，默认会走后续流程，将当前数据的版本数据设置为当前版本
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
            }

            $this->{$this->currentVersionValueKey()} = $model->{$this->relationVersionValueLocalKey()};
            $this->save();
        };

        // 检测是否在事务中
        $connection = $this->getConnection();
        if (!$connection->transactionLevel()) {
            $connection->transaction(function () use ($process) {
                $process();
            });
        } else {
            $process();
        }
    }

    /**
     * 切换到指定版本
     *
     * @param string $versionId 版本 hash
     * @param bool $checkVersionExists
     * @return bool
     */
    public function switchTo(string $versionId, bool $checkVersionExists = false): bool
    {
        if ($checkVersionExists) {
            $version = $this->versionDataItems()->where('version_id', $versionId)->exists();
            if ($version === null) {
                throw new InvalidArgumentException('Version not exists');
            }
        }

        $this->{$this->currentVersionValueKey()} = $versionId;
        return $this->save();
    }
}