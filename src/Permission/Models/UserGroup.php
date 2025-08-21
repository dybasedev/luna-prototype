<?php

namespace Dybasedev\LunaPrototype\Permission\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\UserGroupContract;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\PermissionBinding;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * 默认用户组模型实现
 * 
 * 业务系统可以使用自己的用户组模型，只需实现 UserGroupContract 接口
 * 
 * @property string $id
 * @property string $name 组名称
 * @property string|null $description 组描述
 * @property array|null $metadata 元数据
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserGroup extends Model implements UserGroupContract, PermissionSubject, Backupable
{
    use NamedId, BackupableModel;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_permission_user_groups';

    /**
     * 可填充字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'metadata',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * 获取组成员关系
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany
    {
        // 从配置获取用户模型，避免依赖注入问题
        $userModel = LunaPermissionConfigure::getUserModelClass() ?: 'App\\Models\\User';
        
        return $this->belongsToMany(
            $userModel,
            'luna_permission_user_group_members',
            'group_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * 获取组的策略分配
     *
     * @return HasMany
     */
    public function policyAssignments(): HasMany
    {
        $configure = app(LunaPermissionConfigure::class);
        
        return $this->hasMany($configure->policyAssignmentModel, 'subject_id')
            ->where('subject_type', hash_code($this->getSubjectType()));
    }

    /**
     * 获取组ID
     *
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->id;
    }

    /**
     * 获取组名称
     *
     * @return string
     */
    public function getGroupName(): string
    {
        return $this->name;
    }

    /**
     * 获取组描述
     *
     * @return string|null
     */
    public function getGroupDescription(): ?string
    {
        return $this->description;
    }

    /**
     * 检查用户是否在组内
     *
     * @param mixed $user
     * @return bool
     */
    public function hasMember($user): bool
    {
        $userId = is_object($user) ? $user->getKey() : $user;
        return \DB::table('luna_permission_user_group_members')
            ->where('group_id', $this->id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 添加组成员
     *
     * @param mixed $user
     * @return void
     */
    public function addMember($user): void
    {
        $userId = is_object($user) ? $user->getKey() : $user;
        
        if (!$this->hasMember($userId)) {
            \DB::table('luna_permission_user_group_members')->insert([
                'group_id' => $this->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 移除组成员
     *
     * @param mixed $user
     * @return void
     */
    public function removeMember($user): void
    {
        $userId = is_object($user) ? $user->getKey() : $user;
        
        \DB::table('luna_permission_user_group_members')
            ->where('group_id', $this->id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * 获取主体类型
     *
     * @return string
     */
    public function getSubjectType(): string
    {
        return 'group';
    }

    /**
     * 获取主体ID
     *
     * @return string
     */
    public function getSubjectId(): string
    {
        return $this->id;
    }

    /**
     * 获取主体标识符
     *
     * @return string
     */
    public function getSubjectIdentifier(): string
    {
        return sprintf('%s:%s', $this->getSubjectType(), $this->getSubjectId());
    }

    /**
     * 获取主体显示名称
     *
     * @return string
     */
    public function getSubjectDisplayName(): string
    {
        return $this->name;
    }

    /**
     * 启动模型
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        // 删除前清理相关数据
        static::deleting(function (UserGroup $group) {
            // 清理组成员关系
            \DB::table('luna_permission_user_group_members')
                ->where('group_id', $group->id)
                ->delete();
            
            // 清理策略分配
            $group->policyAssignments()->delete();
        });
    }
}