<?php

namespace Dybasedev\LunaPrototype\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\PermissionBinding;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 策略分配模型
 * 
 * 记录策略与主体（用户、角色、用户组）的关联关系
 * 
 * @property int $id
 * @property string $policy_id 策略ID
 * @property int $subject_type 主体类型哈希值
 * @property string $subject_id 主体ID
 * @property array|null $conditions 附加条件
 * @property \Carbon\Carbon|null $expires_at 过期时间
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Policy $policy
 */
class PolicyAssignment extends Model
{

    /**
     * 主体类型常量
     */
    public const TYPE_USER = 'user';
    public const TYPE_ROLE = 'role';
    public const TYPE_GROUP = 'group';

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_permission_policy_assignments';

    /**
     * 可填充字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'policy_id',
        'subject_type',
        'subject_id',
        'conditions',
        'expires_at',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subject_type' => 'integer',
        'conditions' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * 获取关联的策略
     *
     * @return BelongsTo
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    /**
     * 获取主体模型
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getSubject(): ?Model
    {
        $modelClass = $this->getSubjectModelClass();
        if (!$modelClass) {
            return null;
        }

        return $modelClass::query()->find($this->subject_id);
    }

    /**
     * 获取主体模型类
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    protected function getSubjectModelClass(): ?string
    {
        $configure = app(LunaPermissionConfigure::class);
        
        return match ($this->subject_type) {
            hash_code(self::TYPE_USER) => $this->getUserModelClass(),
            hash_code(self::TYPE_ROLE) => $configure->roleModel,
            hash_code(self::TYPE_GROUP) => $configure->userGroupContract ?? UserGroup::class,
            default => null,
        };
    }

    /**
     * 获取用户模型类
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getUserModelClass(): string
    {
        $binding = app(PermissionBinding::class);
        return $binding ? $binding->getTargetClass() : 'App\\Models\\User';
    }

    /**
     * 按主体查询
     *
     * @param Builder $query
     * @param string $type
     * @param string $id
     * @return Builder
     */
    public function scopeBySubject(Builder $query, string $type, string $id): Builder
    {
        return $query->where('subject_type', hash_code($type))->where('subject_id', $id);
    }

    /**
     * 查询未过期的分配
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 检查是否已过期
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }


    /**
     * 创建策略分配
     *
     * @param Policy|string $policy
     * @param PermissionSubject $subject
     * @param array $options
     * @return static
     */
    public static function assign($policy, PermissionSubject $subject, array $options = []): static
    {
        if (is_string($policy)) {
            $policy = Policy::findByName($policy);
        }

        if (!$policy) {
            throw new \InvalidArgumentException('Policy not found');
        }

        return static::create(array_merge([
            'policy_id' => (string) $policy->id,
            'subject_type' => (int) hash_code($subject->getSubjectType()),
            'subject_id' => (string) $subject->getSubjectId(),
        ], $options));
    }
}