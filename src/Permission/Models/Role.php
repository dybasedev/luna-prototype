<?php

namespace Dybasedev\LunaPrototype\Permission\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Backupable;
use Dybasedev\LunaPrototype\Foundation\BackupableModel;
use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * 角色模型
 * 
 * 角色是一种特殊的权限主体，可以理解为非实体用户（如系统、第三方接口等）
 * 
 * @property string $id
 * @property string $name 角色名称（唯一标识）
 * @property string $display_name 显示名称
 * @property string|null $description 角色描述
 * @property array|null $metadata 元数据
 * @property bool $is_system 是否系统角色
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Role extends Model implements PermissionSubject, Backupable
{
    use NamedId, BackupableModel;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_permission_roles';

    /**
     * 可填充字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'metadata',
        'is_system',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'is_system' => 'boolean',
    ];

    /**
     * 默认属性值
     *
     * @var array
     */
    protected $attributes = [
        'is_system' => false,
    ];

    /**
     * 获取角色的策略分配
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
     * 获取主体类型
     *
     * @return string
     */
    public function getSubjectType(): string
    {
        return 'role';
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
        return $this->display_name ?: $this->name;
    }

    /**
     * 通过名称查找角色
     *
     * @param string $name
     * @return static|null
     */
    public static function findByName(string $name): ?static
    {
        return static::query()->where('name', $name)->first();
    }

    /**
     * 创建系统角色
     *
     * @param string $name
     * @param string $displayName
     * @param array $attributes
     * @return static
     */
    public static function createSystemRole(string $name, string $displayName, array $attributes = []): static
    {
        return static::query()->create(array_merge([
            'name' => $name,
            'display_name' => $displayName,
            'is_system' => true,
        ], $attributes));
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
        static::deleting(function (Role $role) {
            // 系统角色不允许删除
            if ($role->is_system) {
                throw LunaException::create('系统角色不允许删除')
                    ->withDisplayMessage('角色 "' . $role->display_name . '" 是系统角色，不能删除');
            }
            
            // 清理策略分配
            $role->policyAssignments()->delete();
        });
    }
}