<?php

namespace Dybasedev\LunaPrototype\Permission\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\VersionControlModel\VersionControl;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * 权限策略模型
 * 
 * 策略使用版本控制管理不同版本的声明内容
 * 
 * @property string $id
 * @property string $name 策略名称（标识符）
 * @property string|null $description 策略描述
 * @property string $current_version_id 当前版本ID
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read PolicyVersion|null $current
 * @property-read Collection<PolicyVersion> $versions
 */
class Policy extends Model implements Backupable
{
    use NamedId;
    use VersionControl;
    use BackupableModel;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_permission_policies';

    /**
     * 可填充字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'current_version_id',
    ];

    /**
     * 版本数据模型
     *
     * @return string
     */
    public function versionValueModel(): string
    {
        return PolicyVersion::class;
    }

    /**
     * 关联版本数据外键
     *
     * @return string
     */
    public function relationVersionValueForeignKey(): string
    {
        return 'policy_id';
    }

    /**
     * 查找策略
     *
     * @param string $name
     * @return static|null
     */
    public static function findByName(string $name): ?static
    {
        return static::query()->where('name', $name)->first();
    }

    /**
     * 按名称查询
     *
     * @param Builder $query
     * @param string|array $names
     * @return Builder
     */
    public function scopeByName(Builder $query, $names): Builder
    {
        if (is_array($names)) {
            return $query->whereIn('name', $names);
        }

        return $query->where('name', $names);
    }

    /**
     * 获取策略声明
     *
     * @return PolicyStatement|null
     */
    public function getStatement(): ?PolicyStatement
    {
        return $this->current ? new PolicyStatement($this->current->statement) : null;
    }

    /**
     * 创建新版本
     *
     * @param array $statement 策略声明
     * @param string|null $comment 版本注释
     * @return void
     */
    public function createVersion(array $statement, ?string $comment = null): void
    {
        // 验证策略声明
        // 如果是多语句格式，验证每个语句
        if (isset($statement['statements']) && is_array($statement['statements'])) {
            foreach ($statement['statements'] as $stmt) {
                $policyStatement = new PolicyStatement($stmt);
                $policyStatement->validate();
            }
        } else {
            $policyStatement = new PolicyStatement($statement);
            $policyStatement->validate();
        }
        
        // 使用 VersionControl 的方法创建版本
        $this->createVersionValue([
            'statement' => $statement,
            'comment' => $comment,
        ]);
    }

    /**
     * 应用策略版本
     *
     * @param string $versionId
     * @return bool
     */
    public function applyVersion(string $versionId): bool
    {
        // 使用 VersionControl 的 switchTo 方法
        return $this->switchTo($versionId, true);
    }

    /**
     * 备份数据迭代器
     * 重写以包含版本数据
     * 
     * @return \Iterator<array>
     */
    public static function backupDatasourceIterator(): \Iterator
    {
        $data = [];
        
        static::query()->with('versions')->chunk(100, function ($policies) use (&$data) {
            foreach ($policies as $policy) {
                $policyData = $policy->toArray();
                
                // 包含所有版本数据
                $policyData['_versions'] = $policy->versions->map(function ($version) {
                    return [
                        'version_id' => $version->version_id,
                        'statement' => $version->statement,
                        'comment' => $version->comment,
                        'created_at' => $version->created_at?->toIso8601String(),
                    ];
                })->toArray();
                
                $data[] = $policyData;
            }
        });
        
        return new \ArrayIterator($data);
    }

    /**
     * 恢复数据
     * 重写以处理版本数据
     * 
     * @param \Iterator $backup
     * @return void
     */
    public static function recoverFromBackupIterator(\Iterator $backup): void
    {
        \DB::transaction(function () use ($backup) {
            foreach ($backup as $data) {
                $versions = $data['_versions'] ?? [];
                unset($data['_versions']);
                
                // 恢复策略主体
                $attributes = $data;
                unset($attributes['created_at'], $attributes['updated_at']);
                
                $policy = static::updateOrCreate(
                    ['name' => $data['name']],
                    $attributes
                );
                
                // 恢复版本
                foreach ($versions as $versionData) {
                    PolicyVersion::updateOrCreate(
                        [
                            'version_id' => $versionData['version_id'],
                            'policy_id' => $policy->id,
                        ],
                        [
                            'statement' => $versionData['statement'],
                            'comment' => $versionData['comment'],
                        ]
                    );
                }
                
                // 更新当前版本指向
                if (isset($data['current_version_id'])) {
                    $policy->current_version_id = $data['current_version_id'];
                    $policy->save();
                }
            }
        });
    }
}