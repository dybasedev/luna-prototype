<?php

namespace Dybasedev\LunaPrototype\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 权限策略版本模型
 * 
 * 存储策略的版本数据，符合 VersionControl trait 的要求
 * 
 * @property string $version_id 版本ID（SHA1）
 * @property string $policy_id 策略ID（外键）
 * @property array $statement 策略声明
 * @property string|null $comment 版本注释
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Policy $policy
 */
class PolicyVersion extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_permission_policy_versions';
    
    /**
     * 主键
     *
     * @var string
     */
    protected $primaryKey = 'version_id';
    
    /**
     * 主键类型
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * 是否自增
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * 可填充字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'version_id',
        'policy_id',
        'statement',
        'comment',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'statement' => 'array',
    ];

    /**
     * 获取所属策略
     *
     * @return BelongsTo
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    /**
     * 获取策略声明
     *
     * @return PolicyStatement
     */
    public function getStatement(): PolicyStatement
    {
        return new PolicyStatement($this->statement ?? []);
    }
}